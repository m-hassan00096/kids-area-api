<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Models\Role;

final class IdentityMutex
{
    public function ensureOuterTransactionAvailable(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Identity access writes must own the outer transaction.');
        }
    }

    public function lock(): Role
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('The Identity manager row must be locked inside a transaction.');
        }

        $manager = Role::query()
            ->where('name', 'manager')
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->first();

        if ($manager === null || $manager->name !== 'manager' || $manager->guard_name !== 'web') {
            throw new BusinessException(__('identity.seed_prerequisites'), errorCode: 'IDENTITY_SEED_REQUIRED');
        }

        return $manager;
    }
}
