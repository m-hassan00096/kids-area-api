<?php

namespace Modules\Activities\Services;

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
use Modules\Identity\Services\CurrentStaffMutation;
use Modules\Identity\Services\IdentityMutex;
use PDOException;
use SensitiveParameter;
use Throwable;

final readonly class ActivityMutation
{
    public function __construct(
        private IdentityMutex $mutex,
        private CurrentStaffMutation $currentStaff,
        private ActivityConstraintErrors $constraints,
    ) {}

    public function execute(#[SensitiveParameter] Request $request, string $permission, Closure $mutation): void
    {
        $this->mutex->ensureOuterTransactionAvailable();
        if (! in_array($permission, ['activities.store', 'activities.update', 'activities.delete'], true)) {
            throw new LogicException('Unsupported Activity mutation capability.');
        }

        $connection = DB::connection();
        $pdo = null;
        $wait = null;
        $state = 'rolled_back';
        $commitAttempted = false;
        $failure = null;

        try {
            $pdo = $connection->getPdo();
            $wait = (int) $connection->selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value', [], false)->value;
            $connection->statement('SET SESSION innodb_lock_wait_timeout = 5');
            $state = 'unknown';
            $connection->transaction(function () use ($request, $permission, $mutation, &$commitAttempted): void {
                $this->mutex->lock();
                // Recheck live actor/capability and original session before
                // obtaining a write target lock or touching the catalogue.
                $this->currentStaff->lockPrepared($request, $permission, static fn () => null);
                $mutation();
                $commitAttempted = true;
            }, 1);
            $state = 'committed';
        } catch (Throwable $exception) {
            $failure = $exception;
            if ($state === 'unknown' && ! $commitAttempted && ! $this->connectionFailure($exception)) {
                try {
                    if ($connection->transactionLevel() === 0 && $connection->getRawPdo() === $pdo
                        && $pdo !== null && ! $pdo->inTransaction()) {
                        $state = 'rolled_back';
                    }
                } catch (Throwable) {
                    // An uncertain transaction is never replayed.
                }
            }
        } finally {
            try {
                if ($state === 'unknown' || ($pdo !== null && $connection->getRawPdo() !== $pdo)) {
                    $connection->disconnect();
                } elseif ($wait !== null) {
                    $connection->statement('SET SESSION innodb_lock_wait_timeout = '.$wait);
                }
            } catch (Throwable $restoreFailure) {
                $connection->disconnect();
                $this->warn($request, 'Activity lock timeout restoration failed.', $restoreFailure);
                if ($failure === null && $state !== 'committed') {
                    $failure = $restoreFailure;
                }
            }
        }

        $request->attributes->set('activities_outcome', $state);
        if ($failure !== null) {
            if ($failure instanceof AuthenticationException && $state === 'rolled_back') {
                try {
                    $this->currentStaff->retireInvalid($request);
                } catch (Throwable $retireFailure) {
                    $this->warn($request, 'Invalid Activity session retirement failed.', $retireFailure);
                }
            }
            if ($state === 'unknown' || $this->connectionFailure($failure)
                || ($failure instanceof PDOException && in_array((int) ($failure->errorInfo[1] ?? 0), [1205, 1213], true))) {
                throw new BusinessException(ApiResponse::text('api.errors.unavailable'), 503);
            }
            if ($state === 'rolled_back' && $failure instanceof QueryException) {
                throw $this->constraints->translate($failure);
            }

            throw $failure;
        }
    }

    private function connectionFailure(#[SensitiveParameter] Throwable $failure): bool
    {
        return $failure instanceof PDOException && in_array((int) ($failure->errorInfo[1] ?? 0), [2002, 2003, 2006, 2013], true);
    }

    private function warn(Request $request, string $message, #[SensitiveParameter] Throwable $failure): void
    {
        try {
            Log::warning($message, ['request_id' => RequestId::id($request), 'exception_class' => $failure::class]);
        } catch (Throwable) {
            // Diagnostics do not determine the database outcome.
        }
    }
}
