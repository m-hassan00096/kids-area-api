<?php

namespace Modules\Activities\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Activities\Enums\ActivityBillingMode;
use Modules\Activities\Http\Resources\ActivityResource;
use Modules\Activities\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ActivitiesQuery
{
    private const COLUMNS = ['id', 'name', 'supports_hourly', 'supports_subscription', 'hourly_rate', 'monthly_price', 'is_active'];

    public function index(array $criteria, Request $request): array
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('Activity list requires its own snapshot.');
        }
        $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

        return $connection->transaction(function () use ($criteria, $request): array {
            $query = $this->filtered($criteria);
            $total = (int) (clone $query)->reorder()->count();
            $page = $criteria['page'];
            $perPage = $criteria['perPage'];
            $lastPage = max(1, intdiv($total, $perPage) + (int) ($total % $perPage !== 0));
            $items = [];
            if ($total > 0 && $page <= $lastPage) {
                // (page - 1) * perPage is bounded by total - 1.
                $items = $query->forPage($page, $perPage)->get(self::COLUMNS)
                    ->map(fn (Activity $activity) => (new ActivityResource($activity))->resolve($request))->all();
            }

            return [
                'items' => $items,
                'pagination' => ['total' => $total, 'currentPage' => $page, 'perPage' => $perPage, 'lastPage' => $lastPage],
            ];
        });
    }

    public function detail(int $id, Request $request): array
    {
        $activity = Activity::query()->useWritePdo()->whereKey($id)->first(self::COLUMNS);
        abort_if($activity === null, 404);

        return (new ActivityResource($activity))->resolve($request);
    }

    private function filtered(array $criteria): Builder
    {
        // Only validated input reaches Spatie; callbacks use original scalar
        // values, not its comma-split representation of literal search text.
        $filters = Request::create('/api/v1/activities', 'GET', [
            'filter' => $criteria['requested'], 'sort' => $criteria['sort'],
        ]);
        $query = QueryBuilder::for(Activity::query()->useWritePdo(), $filters)->allowedFilters([
            AllowedFilter::callback('search', static function (Builder $query) use ($criteria): void {
                $search = $criteria['search'];
                if ($search !== null && $search !== '') {
                    $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                    $query->whereRaw("activities.name LIKE ? ESCAPE '!'", [$pattern]);
                }
            }),
            AllowedFilter::callback('isActive', static function (Builder $query) use ($criteria): void {
                $query->where('activities.is_active', $criteria['isActive']);
            }),
            AllowedFilter::callback('billingMode', static function (Builder $query) use ($criteria): void {
                $column = match ($criteria['billingMode']) {
                    ActivityBillingMode::HOURLY => 'activities.supports_hourly',
                    ActivityBillingMode::SUBSCRIPTION => 'activities.supports_subscription',
                };
                $query->where($column, true);
            }),
        ])->allowedSorts(['id', 'name'])->getEloquentBuilder();

        if (in_array($criteria['sort'], ['name', '-name'], true)) {
            $query->orderBy('id');
        }

        return $query;
    }
}
