<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use App\Http\Middleware\RequestId;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Modules\Identity\Support\UsersMutationOutcome;
use PDOException;
use SensitiveParameter;
use Modules\Identity\Support\IdentityPermissions;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class IdentityAccessMutation
{
    public function __construct(
        private readonly IdentityMutex $mutex,
        private readonly IdentityAccess $access,
        private readonly CurrentStaffMutation $currentStaff,
        private readonly UserConstraintErrors $constraints,
    ) {}

    /**
     * Reusable transaction boundary for future authorized Identity Actions.
     * The callback must use native Spatie APIs on the same connection. No
     * production Users or Roles CRUD operations are exposed by Feature 01.
     */
    public function execute(int $actorId, string $permission, Closure $mutation): mixed
    {
        $this->mutex->ensureOuterTransactionAvailable();

        try {
            return DB::transaction(function () use ($actorId, $permission, $mutation): mixed {
                $manager = $this->mutex->lock();

                if (! $this->access->currentHasAll([$permission], $actorId)) {
                    throw new BusinessException(__('identity.access_denied'), 403, 'IDENTITY_ACCESS_DENIED');
                }

                $result = $mutation();

                $this->assertPostcondition((int) $manager->id);

                return $result;
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Users-specific boundary. Keep Feature 01's existing execute contract.
     * Caller stages/hashes before this method; only the callback may mutate.
     */
    public function executeUsers(
        #[SensitiveParameter] Request $request,
        string $permission,
        #[SensitiveParameter] Closure $mutation,
        UsersMutationOutcome $outcome,
        array $protectedUserForeignKeys = [],
    ): mixed {
        $this->mutex->ensureOuterTransactionAvailable();
        if (! in_array($permission, ['identity.users.create', 'identity.users.update', 'identity.users.delete'], true)) {
            throw new LogicException('Unsupported Users mutation capability.');
        }

        $connection = DB::connection();
        $originalWait = null;
        $pdo = null;
        $commitAttempted = false;
        $failure = null;
        $outcome->state = 'rolled_back';
        $outcome->retainFiles = false;
        try {
            $pdo = $connection->getPdo();
            $originalWait = (int) $connection->selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value', [], false)->value;
            $connection->statement('SET SESSION innodb_lock_wait_timeout = 5');
            $outcome->state = 'unknown';
            $result = $connection->transaction(function () use ($request, $permission, $mutation, &$commitAttempted): mixed {
                $manager = $this->mutex->lock();
                [$actor, $target] = $this->currentStaff->lock($request, $permission);
                $result = $mutation($actor, $target);
                $this->assertPostcondition((int) $manager->id);
                $commitAttempted = true;

                return $result;
            }, 1);
            $outcome->state = 'committed';
        } catch (Throwable $exception) {
            $failure = $exception;
            // A commit acknowledgement may be lost even when MySQL committed.
            // Only positively observed pre-commit rollback permits cleanup.
            if ($outcome->state === 'unknown' && ! $commitAttempted && ! $this->isConnectionFailure($exception)) {
                try {
                    if ($connection->transactionLevel() === 0 && $connection->getRawPdo() === $pdo
                        && $pdo !== null && ! $pdo->inTransaction()) {
                        $outcome->state = 'rolled_back';
                    }
                } catch (Throwable) {
                    // Keep the outcome unknown.
                }
            }
        } finally {
            // Never use a disconnected/ambiguous connection to restore a
            // session setting or carry out subsequent authoritative work.
            try {
                if ($outcome->state === 'unknown' || ($pdo !== null && $connection->getRawPdo() !== $pdo)) {
                    $connection->disconnect();
                } elseif ($originalWait !== null) {
                    $connection->statement('SET SESSION innodb_lock_wait_timeout = '.$originalWait);
                }
            } catch (Throwable $restoreFailure) {
                $connection->disconnect();
                $outcome->retainFiles = true;
                $this->warn('Users lock timeout restoration failed.', $restoreFailure);
                // Preserve a primary failure, and do not reinterpret a
                // positively acknowledged commit as rollback.
                if ($failure === null && $outcome->state !== 'committed') {
                    $failure = $restoreFailure;
                }
            }

            try {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (Throwable $cacheFailure) {
                $outcome->retainFiles = true;
                $this->warn('Users permission cache invalidation failed.', $cacheFailure);
                if ($failure === null && $outcome->state !== 'committed') {
                    $failure = $cacheFailure;
                }
            }
        }

        if ($failure !== null) {
            if ($failure instanceof AuthenticationException && $outcome->state === 'rolled_back') {
                try {
                    $this->currentStaff->retireInvalid($request);
                } catch (Throwable $retireFailure) {
                    $this->warn('Invalid Users session retirement failed.', $retireFailure);
                }
            }
            if ($outcome->state === 'unknown' || $this->isConnectionFailure($failure)
                || ($failure instanceof PDOException && in_array((int) ($failure->errorInfo[1] ?? 0), [1205, 1213], true))) {
                throw new BusinessException(ApiResponse::text('api.errors.unavailable'), 503);
            }
            if ($outcome->state === 'rolled_back' && $failure instanceof QueryException) {
                throw $this->constraints->translate($failure, $protectedUserForeignKeys);
            }

            throw $failure;
        }

        return $result;
    }

    private function assertPostcondition(int $managerId): void
    {
        $currentManager = $this->mutex->lock();
        $reception = DB::table('roles')->where('name', 'reception')->where('guard_name', 'web')
            ->lockForUpdate()->first();

        if ((int) $currentManager->id !== $managerId || ! $currentManager->is_active
            || $reception === null || $reception->name !== 'reception'
            || $reception->guard_name !== 'web' || ! (bool) $reception->is_active) {
            throw new BusinessException(__('identity.permanent_roles'), errorCode: 'IDENTITY_PERMANENT_ROLES');
        }
        if (! $this->access->currentHasAll(IdentityPermissions::ESSENTIAL)) {
            throw new BusinessException(__('identity.last_admin'), errorCode: 'IDENTITY_LAST_ADMIN');
        }
    }

    private function isConnectionFailure(#[SensitiveParameter] Throwable $failure): bool
    {
        return $failure instanceof PDOException && in_array((int) ($failure->errorInfo[1] ?? 0), [2002, 2003, 2006, 2013], true);
    }

    private function warn(string $message, #[SensitiveParameter] Throwable $failure): void
    {
        try {
            Log::warning($message, [
                'request_id' => app()->bound('request') ? RequestId::id(request()) : null,
                'exception_class' => $failure::class,
            ]);
        } catch (Throwable) {
            // Diagnostics must not change a transaction outcome.
        }
    }
}
