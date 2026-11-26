<?php

namespace Modules\Identity\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as SqlBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Http\Resources\UserManagementResource;
use Modules\Identity\Models\User;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class UsersQuery
{
    public function index(array $criteria, Request $request): array
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('Users index requires its own read snapshot.');
        }

        // One next-transaction setting, not a session/global isolation change.
        // Count, page and role reads share a primary-backed, read-only snapshot.
        $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

        return $connection->transaction(function () use ($criteria, $request): array {
            $query = $this->filtered($criteria);
            $total = (int) (clone $query)->count();
            $page = $criteria['page'];
            $perPage = $criteria['perPage'];
            $lastPage = max(1, intdiv($total, $perPage) + (int) ($total % $perPage !== 0));
            $items = [];
            // Do not multiply an unbounded page number into an overflowing
            // OFFSET, or issue a needless page query past the result set.
            if ($total > 0 && $page <= $lastPage) {
                $users = $query->orderByDesc('users.id')->forPage($page, $perPage)
                    ->get(['users.id', 'users.name', 'users.username', 'users.phone', 'users.is_active', 'users.avatar_key']);
                $roles = $this->rolesFor($users->modelKeys());
                foreach ($users as $user) {
                    $items[] = (new UserManagementResource($user, $this->singleRole($roles, (int) $user->id)))->resolve($request);
                }
            }

            return [
                'items' => $items,
                'pagination' => ['total' => $total, 'currentPage' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage],
            ];
        });
    }

    public function detail(User $target, Request $request): array
    {
        $roles = $this->rolesFor([(int) $target->id]);

        return (new UserManagementResource($target, $this->singleRole($roles, (int) $target->id), true))->resolve($request);
    }

    private function filtered(array $criteria): Builder
    {
        // Only validated query keys reach the package, never the original
        // body or copied staff authorization state. Callbacks close over the
        // typed values because v6.4.4 splits commas and converts true/false.
        $filters = Request::create('/api/v1/users', 'GET', ['filter' => $criteria['requested']]);

        return QueryBuilder::for(User::query()->useWritePdo(), $filters)->allowedFilters([
            AllowedFilter::callback('search', static function (Builder $query) use ($criteria): void {
                $search = $criteria['search'];
                if ($search === null || $search === '') {
                    return;
                }
                $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->where(static function (Builder $group) use ($pattern, $search): void {
                    $group->whereRaw("users.name LIKE ? ESCAPE '!'", [$pattern]);
                    // Username and phone are ASCII-only columns. A non-ASCII
                    // literal cannot match either; avoid incompatible coercion.
                    if (preg_match('/\A[\x00-\x7F]*\z/', $search) === 1) {
                        $group->orWhereRaw("users.username LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("users.phone LIKE ? ESCAPE '!'", [$pattern]);
                    }
                });
            }),
            AllowedFilter::callback('roleId', static function (Builder $query) use ($criteria): void {
                $morph = (new User)->getMorphClass();
                $query->whereExists(static function (SqlBuilder $subquery) use ($criteria, $morph): void {
                    $subquery->selectRaw('1')->from('model_has_roles as mr')
                        ->join('roles as r', 'r.id', '=', 'mr.role_id')
                        ->whereColumn('mr.model_id', 'users.id')
                        ->where('mr.model_type', $morph)->whereRaw('BINARY mr.model_type = ?', [$morph])
                        ->where('r.id', $criteria['roleId'])
                        ->where('r.guard_name', 'web')->whereRaw('BINARY r.guard_name = ?', ['web']);
                });
            }),
            AllowedFilter::callback('isActive', static function (Builder $query) use ($criteria): void {
                $query->where('users.is_active', $criteria['isActive']);
            }),
        ])->getEloquentBuilder();
    }

    private function rolesFor(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $morph = (new User)->getMorphClass();

        return DB::table('model_has_roles as mr')->useWritePdo()
            ->leftJoin('roles as r', 'r.id', '=', 'mr.role_id')
            ->whereIn('mr.model_id', $ids)
            ->where('mr.model_type', $morph)->whereRaw('BINARY mr.model_type = ?', [$morph])
            ->get(['mr.model_id', 'r.id', 'r.name', 'r.guard_name', 'r.is_active'])
            ->groupBy('model_id');
    }

    private function singleRole(Collection $roles, int $userId): ?object
    {
        $memberships = $roles->get($userId, collect());
        if ($memberships->count() !== 1) {
            return null;
        }

        $role = $memberships->first();

        return $role->id !== null && $role->guard_name === 'web' ? $role : null;
    }
}
