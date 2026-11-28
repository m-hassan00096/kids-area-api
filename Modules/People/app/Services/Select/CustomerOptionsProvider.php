<?php

namespace Modules\People\Services\Select;

use App\Services\Select\SelectProvider;
use App\Support\SensitiveQueryScope;
use Illuminate\Support\Facades\DB;
use Modules\People\Enums\CustomerStatus;

final class CustomerOptionsProvider implements SelectProvider
{
    public function allows(array $permissions): bool
    {
        return in_array('people.customers.show', $permissions, true);
    }

    public function options(): array
    {
        $connection = DB::connection();

        return SensitiveQueryScope::run($connection, static function () use ($connection): array {
            $rows = $connection->table('customers')->useWritePdo()
                ->where('is_active', CustomerStatus::ACTIVE->value)
                ->orderBy('name')->orderBy('id')->get(['id', 'name', 'mobile']);

            return $rows->map(static fn (object $row): array => [
                'value' => (int) $row->id,
                'label' => $row->mobile === null ? $row->name : $row->name.' - '.$row->mobile,
            ])->all();
        });
    }
}
