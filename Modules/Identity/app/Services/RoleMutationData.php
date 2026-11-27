<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\PermissionCatalogue;

final class RoleMutationData
{
    public function target(Request $request): ?Role
    {
        $this->requireTransaction();
        $id = $request->attributes->get('roles_target_id');
        if (! is_int($id)) {
            return null;
        }

        return Role::query()->whereKey($id)->where('guard_name', 'web')
            ->whereRaw('BINARY guard_name = ?', ['web'])->lockForUpdate()->first();
    }

    public function name(string $name, ?Role $target = null): void
    {
        $this->requireTransaction();
        // The actual unique collation, not PHP case folding, is authoritative.
        if (Role::query()->where('name', $name)->where('guard_name', 'web')
            ->when($target !== null, fn ($query) => $query->where('id', '!=', $target->id))
            ->lockForUpdate()->first(['id']) !== null) {
            throw ValidationException::withMessages(['name' => ApiResponse::text('identity.role_duplicate')]);
        }
    }

    public function permissions(array $keys, ?Role $target = null): Collection
    {
        $this->requireTransaction();
        // Lock all approved catalogue candidates in stable order, and check
        // actual stored grants before replacing them. Do not silently erase
        // an unregistered or foreign-guard assignment.
        $definitions = Permission::query()->where('guard_name', 'web')->whereIn('name', PermissionCatalogue::keys())
            ->orderBy('id')->lockForUpdate()->get()->filter(
                fn (Permission $permission) => $permission->guard_name === 'web'
                    && in_array($permission->name, PermissionCatalogue::keys(), true)
            )->keyBy('name');
        if ($target !== null) {
            $grants = DB::table('role_has_permissions')->where('role_id', $target->id)
                ->orderBy('permission_id')->lockForUpdate()->pluck('permission_id')->map(fn ($id) => (int) $id)->all();
            $valid = $definitions->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (array_diff($grants, $valid) !== []) {
                $this->integrity();
            }
        }
        if (array_diff($keys, $definitions->keys()->all()) !== []) {
            throw ValidationException::withMessages(['permissions' => ApiResponse::text('api.errors.validation')]);
        }

        return collect($keys)->map(function (string $name) use ($definitions): Permission {
            $permission = $definitions->get($name);
            if (! is_string($permission->alias_name) || trim($permission->alias_name) === '') {
                $this->integrity();
            }

            return $permission;
        });
    }

    public function members(Role $role): Collection
    {
        $this->requireTransaction();
        $rows = DB::table('model_has_roles')->where('role_id', $role->id)
            ->orderBy('model_type')->orderBy('model_id')->lockForUpdate()->get(['model_type', 'model_id']);
        if ($rows->contains(fn ($row) => $row->model_type !== (new User)->getMorphClass())) {
            $this->integrity();
        }
        $ids = $rows->pluck('model_id')->all();
        $users = User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id', 'auth_version']);
        if ($users->count() !== count($ids)) {
            $this->integrity();
        }

        return $users;
    }

    public function requireUnassigned(Role $role): void
    {
        $this->requireTransaction();
        if (DB::table('model_has_roles')->where('role_id', $role->id)->orderBy('model_id')->lockForUpdate()->first() !== null) {
            throw new BusinessException(ApiResponse::text('identity.role_assigned'), 409, 'IDENTITY_ROLE_ASSIGNED');
        }
    }

    public function protected(Role $role): bool
    {
        return in_array($role->name, ['manager', 'reception'], true);
    }

    public function rejectProtected(): never
    {
        throw new BusinessException(ApiResponse::text('identity.role_protected'), 409, 'IDENTITY_ROLE_PROTECTED');
    }

    private function integrity(): never
    {
        throw new BusinessException(ApiResponse::text('identity.role_integrity'), 409, 'IDENTITY_ROLE_INTEGRITY');
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() !== 1) {
            throw new LogicException('Role data requires the caller-owned Identity transaction.');
        }
    }
}
