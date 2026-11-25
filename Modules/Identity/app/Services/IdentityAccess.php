<?php

namespace Modules\Identity\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Models\User;

final class IdentityAccess
{
    public function isEligible(int $userId): bool
    {
        return $this->eligibleUsers()->where('u.id', $userId)->exists();
    }

    public function allows(int $userId, string $permission): bool
    {
        return $this->eligibleUsers()
            ->where('u.id', $userId)
            ->whereExists(function (Builder $query) use ($permission): void {
                $query->selectRaw('1')
                    ->from('role_has_permissions as rp')
                    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                    ->whereColumn('rp.role_id', 'r.id')
                    ->whereRaw('BINARY p.name = ?', [$permission])
                    ->whereRaw('BINARY p.guard_name = ?', ['web']);
            })
            ->exists();
    }

    public function context(int $userId): ?array
    {
        $rows = $this->eligibleUsers()->where('u.id', $userId)
            ->leftJoin('role_has_permissions as rp', 'rp.role_id', '=', 'r.id')
            ->leftJoin('permissions as p', function ($join): void {
                $join->on('p.id', '=', 'rp.permission_id')
                    ->whereRaw('BINARY p.guard_name = ?', ['web']);
            })
            ->get(['u.name', 'u.username', 'r.id as role_id', 'r.name as role_name', 'r.alias_name', 'p.name as permission_name']);

        $first = $rows->first();
        if ($first === null) {
            return null;
        }

        $permissions = $rows->pluck('permission_name')->filter(fn ($name) => is_string($name))
            ->uniqueStrict()->sort(SORT_STRING)->values()->all();

        return [
            'name' => $first->name,
            'username' => $first->username,
            'role' => ['id' => (int) $first->role_id, 'name' => $first->role_name, 'aliasName' => $first->alias_name],
            'permissions' => $permissions,
        ];
    }

    private function eligibleUsers(): Builder
    {
        $modelType = (new User)->getMorphClass();

        return DB::table('users as u')
            ->useWritePdo()
            ->join('model_has_roles as mr', 'mr.model_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->whereRaw('BINARY mr.model_type = ?', [$modelType])
            ->where('u.is_active', true)
            ->where('r.is_active', true)
            ->whereRaw('BINARY r.guard_name = ?', ['web'])
            ->whereNotExists(function (Builder $query) use ($modelType): void {
                $query->selectRaw('1')
                    ->from('model_has_roles as other')
                    ->whereColumn('other.model_id', 'u.id')
                    ->where('other.model_type', $modelType)
                    ->whereColumn('other.role_id', '!=', 'mr.role_id');
            })
            ->whereNotExists(function (Builder $query) use ($modelType): void {
                $query->selectRaw('1')
                    ->from('model_has_permissions as mp')
                    ->whereColumn('mp.model_id', 'u.id')
                    ->where('mp.model_type', $modelType);
            });
    }

    /**
     * Current, locking reads for the small staff RBAC catalogue, only while the
     * permanent manager mutex is held by the outer transaction. Normal reads
     * never take this mutex. Lock roles, users, then pivots in stable key order.
     */
    public function currentHasAll(array $required, ?int $userId = null): bool
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Current access checks require the Identity transaction.');
        }

        $required = array_values(array_unique($required));
        $modelType = (new User)->getMorphClass();

        // Include inactive rows so later mutation targets are locked in the same order.
        $roles = DB::table('roles')->orderBy('id')->lockForUpdate()->get()
            ->filter(fn ($role) => (bool) $role->is_active && $role->guard_name === 'web')
            ->keyBy('id');

        $users = DB::table('users')->orderBy('id')->lockForUpdate()->get(['id', 'is_active'])
            ->filter(fn ($user) => (bool) $user->is_active && ($userId === null || (int) $user->id === $userId));

        if ($users->isEmpty() || $roles->isEmpty()) {
            return false;
        }

        $userIds = $users->pluck('id')->all();
        $memberships = DB::table('model_has_roles')
            ->where('model_type', $modelType)->whereIn('model_id', $userIds)
            ->orderBy('model_id')->orderBy('role_id')->lockForUpdate()
            ->get()->groupBy('model_id');

        $direct = DB::table('model_has_permissions')
            ->where('model_type', $modelType)->whereIn('model_id', $userIds)
            ->orderBy('model_id')->orderBy('permission_id')->lockForUpdate()
            ->pluck('model_id')->map(fn ($id) => (int) $id)->all();

        $definitions = DB::table('permissions')
            ->whereIn('name', $required)->where('guard_name', 'web')
            ->orderBy('id')->lockForUpdate()->get(['id', 'name', 'guard_name'])
            ->filter(fn ($permission) => $permission->guard_name === 'web' && in_array($permission->name, $required, true));

        if ($definitions->pluck('name')->unique()->count() !== count($required)) {
            return false;
        }

        $permissionIds = $definitions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $grants = DB::table('role_has_permissions')
            ->whereIn('role_id', $roles->keys()->all())->whereIn('permission_id', $permissionIds)
            ->orderBy('role_id')->orderBy('permission_id')->lockForUpdate()
            ->get()->groupBy('role_id');

        foreach ($users as $user) {
            $membership = $memberships->get($user->id, collect());
            if ($membership->count() !== 1 || in_array((int) $user->id, $direct, true)) {
                continue;
            }

            $pivot = $membership->first();
            if ($pivot->model_type !== $modelType || ! $roles->has($pivot->role_id)) {
                continue;
            }

            $actual = $grants->get($pivot->role_id, collect())
                ->pluck('permission_id')->map(fn ($id) => (int) $id)->all();
            if (array_diff($permissionIds, $actual) === []) {
                return true;
            }
        }

        return false;
    }
}
