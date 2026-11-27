<?php

namespace Modules\Activities\Services\Select;

use App\Services\Select\SelectProvider;
use Illuminate\Support\Facades\DB;

final readonly class ActivityOptionsProvider implements SelectProvider
{
    public function __construct(private bool $activeOnly) {}

    public function allows(array $permissions): bool
    {
        return in_array('activities.index', $permissions, true);
    }

    public function options(): array
    {
        $rows = DB::table('activities')->useWritePdo()
            ->when($this->activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);

        return $rows->map(static fn (object $row): array => ['value' => (int) $row->id, 'label' => $row->name])->all();
    }
}
