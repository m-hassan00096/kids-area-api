<?php

namespace Modules\Identity\Actions;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\CustomerCatalogueGrants;
use Modules\Identity\Services\IdentityMutex;
use Modules\Identity\Support\PermissionCatalogue;
use PDOException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class ProvisionCustomerCatalogueAction
{
    public function __construct(private readonly IdentityMutex $mutex, private readonly CustomerCatalogueGrants $grants) {}

    public function execute(): bool
    {
        $this->mutex->ensureOuterTransactionAvailable();
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
            $applied = $connection->transaction(function () use (&$commitAttempted): bool {
                $manager = $this->mutex->lock();
                $reception = Role::query()->where('name', 'reception')->where('guard_name', 'web')->lockForUpdate()->first();
                if ($reception === null) {
                    throw new BusinessException(__('identity.seed_prerequisites'), errorCode: 'IDENTITY_SEED_REQUIRED');
                }

                $definitions = Permission::query()->where('guard_name', 'web')->whereIn('name', PermissionCatalogue::keys())
                    ->orderBy('id')->lockForUpdate()->get();
                $applied = $this->grants->complete($manager, $reception, $definitions, fresh: false);
                $commitAttempted = true;

                return $applied;
            }, 1); // Never replay a possibly committed first grant.
            $state = 'committed';
        } catch (Throwable $exception) {
            $failure = $exception;
            $connectionFailure = $exception instanceof PDOException
                && in_array((int) ($exception->errorInfo[1] ?? 0), [2002, 2003, 2006, 2013], true);
            if ($state === 'unknown' && ! $commitAttempted && ! $connectionFailure) {
                try {
                    if ($connection->transactionLevel() === 0 && $connection->getRawPdo() === $pdo
                        && $pdo !== null && ! $pdo->inTransaction()) {
                        $state = 'rolled_back';
                    }
                } catch (Throwable) {
                    // Remain uncertain.
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
                $this->warn('Customer provisioning lock timeout restoration failed.', $restoreFailure);
                if ($failure === null && $state !== 'committed') {
                    $failure = $restoreFailure;
                }
            }

            try {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (Throwable $cacheFailure) {
                $this->warn('Customer provisioning cache invalidation failed.', $cacheFailure);
                if ($failure === null && $state !== 'committed') {
                    $failure = $cacheFailure;
                }
            }
        }

        if ($failure !== null) {
            if ($state === 'unknown') {
                throw new BusinessException(__('identity.customer_provision_uncertain'), 503, 'IDENTITY_CUSTOMER_PROVISION_UNCERTAIN');
            }

            throw $failure;
        }

        return $applied;
    }

    private function warn(string $message, Throwable $failure): void
    {
        try {
            Log::warning($message, ['exception_class' => $failure::class]);
        } catch (Throwable) {
            // Never reinterpret the database outcome because diagnostics failed.
        }
    }
}
