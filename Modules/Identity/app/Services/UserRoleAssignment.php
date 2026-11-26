<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use SensitiveParameter;

final class UserRoleAssignment
{
    // Called only within the serialized Identity mutation, after ordered
    // current access and target locks. Never silently repair direct grants.
    public function current(#[SensitiveParameter] User $target): ?Role
    {
        if (DB::transactionLevel() !== 1) {
            throw new LogicException('Role assignment requires the outer Identity transaction.');
        }
        $memberships = DB::table('model_has_roles')->where('model_type', $target->getMorphClass())
            ->where('model_id', $target->id)->orderBy('role_id')->lockForUpdate()->get();
        $direct = DB::table('model_has_permissions')->where('model_type', $target->getMorphClass())
            ->where('model_id', $target->id)->orderBy('permission_id')->lockForUpdate()->get();
        if ($memberships->count() > 1 || $direct->isNotEmpty()) {
            $this->anomaly();
        }
        if ($memberships->isEmpty()) {
            return null;
        }
        $membership = $memberships->first();
        $role = Role::query()->whereKey($membership->role_id)->lockForUpdate()->first();
        if ($membership->model_type !== $target->getMorphClass() || $role === null || $role->guard_name !== 'web') {
            $this->anomaly();
        }

        return $role;
    }

    public function requested(int $roleId, ?Role $current = null, bool $reactivating = false): Role
    {
        if (DB::transactionLevel() !== 1) {
            throw new LogicException('Role assignment requires the outer Identity transaction.');
        }
        $role = Role::query()->whereKey($roleId)->lockForUpdate()->first();
        if ($role === null || $role->guard_name !== 'web'
            || (! $role->is_active && ($current?->id !== $roleId || $reactivating))) {
            throw ValidationException::withMessages(['roleId' => ApiResponse::text('api.errors.validation')]);
        }

        return $role;
    }

    private function anomaly(): never
    {
        throw new BusinessException(ApiResponse::text('identity.user_role_anomaly'), 409, 'IDENTITY_USER_ROLE_ANOMALY');
    }
}
