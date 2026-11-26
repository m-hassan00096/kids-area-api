<?php

namespace Modules\Identity\Queries;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as SqlBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Http\Resources\RoleResource;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\PermissionCatalogue;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class RolesQuery
{
    public function index(array $criteria, Request $request): array
    {
        return $this->snapshot(function () use ($criteria, $request): array {
            $query = $this->filtered($criteria);
            $total = (int) (clone $query)->count();
            $page = $criteria['page'];
            $perPage = $criteria['perPage'];
            $lastPage = max(1, intdiv($total, $perPage) + (int) ($total % $perPage !== 0));
            $items = [];
            if ($total > 0 && $page <= $lastPage) {
                $roles = $query->orderByDesc('roles.id')->forPage($page, $perPage)
                    ->get(['roles.id', 'roles.name', 'roles.is_active']);
                $ids = $roles->modelKeys();
                $users = $this->userCounts($ids);
                $permissions = $this->permissionCounts($ids);
                foreach ($roles as $role) {
                    $items[] = (new RoleResource($role, (int) $users->get($role->id, 0),
                        (int) $permissions->get($role->id, 0)))->resolve($request);
                }
            }

            return [
                'items' => $items,
                'pagination' => ['total' => $total, 'currentPage' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage],
            ];
        });
    }

    public function detail(int $id, Request $request): array
    {
        return $this->snapshot(function () use ($id, $request): array {
            // Re-resolve the admitted target inside the same primary snapshot.
            $role = $this->roles()->whereKey($id)->first(['roles.id', 'roles.name', 'roles.is_active']);
            abort_if($role === null, 404);
            $users = $this->userCounts([$id]);
            $permissions = $this->grants([$id])->orderBy('p.name')->get(['p.name', 'p.alias_name'])
                ->map(static function (object $permission): array {
                    if (! is_string($permission->alias_name) || trim($permission->alias_name) === '') {
                        throw new LogicException('Missing stored permission label.');
                    }

                    return ['name' => $permission->name, 'aliasName' => $permission->alias_name];
                })->all();

            return (new RoleResource($role, (int) $users->get($id, 0), count($permissions), $permissions))->resolve($request);
        });
    }

    private function snapshot(Closure $read): array
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('Roles read requires its own snapshot.');
        }
        $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

        return $connection->transaction($read);
    }

    private function roles(): Builder
    {
        return Role::query()->useWritePdo()
            ->where('roles.guard_name', 'web')->whereRaw('BINARY roles.guard_name = ?', ['web']);
    }

    private function filtered(array $criteria): Builder
    {
        $filters = Request::create('/api/v1/roles', 'GET', ['filter' => $criteria['requested']]);

        return QueryBuilder::for($this->roles(), $filters)->allowedFilters([
            AllowedFilter::callback('search', static function (Builder $query) use ($criteria): void {
                $search = $criteria['search'];
                if ($search === null || $search === '') {
                    return;
                }
                $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->whereRaw("roles.name LIKE ? ESCAPE '!'", [$pattern]);
            }),
            AllowedFilter::callback('isActive', static function (Builder $query) use ($criteria): void {
                $query->where('roles.is_active', $criteria['isActive']);
            }),
        ])->getEloquentBuilder();
    }

    private function userCounts(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }
        $morph = (new User)->getMorphClass();

        return DB::table('model_has_roles as mr')->useWritePdo()->join('users as u', 'u.id', '=', 'mr.model_id')
            ->whereIn('mr.role_id', $ids)->where('mr.model_type', $morph)
            ->whereRaw('BINARY mr.model_type = ?', [$morph])
            ->groupBy('mr.role_id')->selectRaw('mr.role_id, COUNT(*) AS aggregate')->pluck('aggregate', 'mr.role_id');
    }

    private function grants(array $ids): SqlBuilder
    {
        return PermissionCatalogue::constrain(DB::table('role_has_permissions as rp')->useWritePdo()
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')->whereIn('rp.role_id', $ids));
    }

    private function permissionCounts(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return $this->grants($ids)->groupBy('rp.role_id')
            ->selectRaw('rp.role_id, COUNT(*) AS aggregate')->pluck('aggregate', 'rp.role_id');
    }
}
