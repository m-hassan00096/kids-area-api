<?php

namespace Modules\Activities\Services\Select;

use App\Exceptions\BusinessException;
use App\Services\Select\SelectProvider;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class ActivityOptionsProvider implements SelectProvider
{
    public function __construct(private bool $activeOnly) {}

    public function allows(array $permissions): bool
    {
        return in_array('activities.index', $permissions, true);
    }

    public function options(): array
    {
        $limit = config('selects.max_options');
        if (! is_int($limit) || $limit < 1 || $limit > 200) {
            throw new LogicException('Invalid Select option cap.');
        }
        $rows = DB::table('activities')->useWritePdo()
            ->when($this->activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')->orderBy('id')->limit($limit + 1)->get(['id', 'name']);
        if ($rows->count() > $limit) {
            throw new BusinessException(ApiResponse::text('identity.select_options_limit'), 409, 'SELECT_OPTIONS_LIMIT');
        }

        return $rows->map(static fn (object $row): array => ['value' => (int) $row->id, 'label' => $row->name])->all();
    }
}
