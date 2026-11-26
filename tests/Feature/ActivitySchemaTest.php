<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Activities\Enums\ActivityBillingMode;
use Modules\Activities\Http\Resources\ActivityResource;
use Modules\Activities\Models\Activity;

class ActivitySchemaTest extends IdentityDatabaseTestCase
{
    public function test_fresh_catalogue_schema_has_exact_approved_columns_indexes_and_checks(): void
    {
        $this->assertSame(['id', 'name', 'supports_hourly', 'supports_subscription', 'hourly_rate', 'monthly_price', 'is_active', 'created_at', 'updated_at'], Schema::getColumnListing('activities'));
        $this->assertSame(['feature_key', 'completed_at'], Schema::getColumnListing('identity_feature_provisions'));
        $columns = DB::table('information_schema.COLUMNS')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->whereIn('TABLE_NAME', ['activities', 'identity_feature_provisions'])
            ->get(['TABLE_NAME as table_name', 'COLUMN_NAME as name', 'COLUMN_TYPE as type', 'IS_NULLABLE as nullable', 'COLLATION_NAME as collation_name', 'COLUMN_DEFAULT as default_value'])
            ->keyBy(fn ($row) => $row->table_name.'.'.$row->name);

        foreach ([
            ['id', 'bigint unsigned', 'NO'],
            ['name', 'varchar(255)', 'NO'],
            ['supports_hourly', 'tinyint(1)', 'NO'],
            ['supports_subscription', 'tinyint(1)', 'NO'],
            ['hourly_rate', 'decimal(12,2)', 'YES'],
            ['monthly_price', 'decimal(12,2)', 'YES'],
            ['is_active', 'tinyint(1)', 'NO'],
            ['created_at', 'timestamp', 'YES'],
            ['updated_at', 'timestamp', 'YES'],
        ] as [$name, $type, $nullable]) {
            $column = $columns['activities.'.$name];
            $this->assertSame($type, $column->type);
            $this->assertSame($nullable, $column->nullable);
        }
        $this->assertSame('utf8mb4_0900_as_ci', $columns['activities.name']->collation_name);
        $this->assertSame('1', (string) $columns['activities.is_active']->default_value);
        $this->assertNull($columns['activities.supports_hourly']->default_value);
        $this->assertNull($columns['activities.supports_subscription']->default_value);
        $this->assertNull($columns['activities.hourly_rate']->default_value);
        $this->assertNull($columns['activities.monthly_price']->default_value);
        $this->assertSame('varchar(100)', $columns['identity_feature_provisions.feature_key']->type);
        $this->assertSame('ascii_bin', $columns['identity_feature_provisions.feature_key']->collation_name);
        $this->assertSame('NO', $columns['identity_feature_provisions.feature_key']->nullable);
        $this->assertSame('timestamp', $columns['identity_feature_provisions.completed_at']->type);
        $this->assertSame('NO', $columns['identity_feature_provisions.completed_at']->nullable);
        $this->assertNull($columns['identity_feature_provisions.completed_at']->default_value);

        $indexes = DB::table('information_schema.STATISTICS')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->whereIn('TABLE_NAME', ['activities', 'identity_feature_provisions'])
            ->orderBy('TABLE_NAME')->orderBy('INDEX_NAME')->orderBy('SEQ_IN_INDEX')
            ->get(['TABLE_NAME as table_name', 'INDEX_NAME as name', 'COLUMN_NAME as column_name', 'NON_UNIQUE as non_unique']);
        $this->assertSame([
            ['activities', 'PRIMARY', 'id', 0],
            ['activities', 'uq_activities_name', 'name', 0],
            ['identity_feature_provisions', 'PRIMARY', 'feature_key', 0],
        ], $indexes->map(fn ($row) => [$row->table_name, $row->name, $row->column_name, (int) $row->non_unique])->all());
        $checks = DB::table('information_schema.TABLE_CONSTRAINTS')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'activities')->where('CONSTRAINT_TYPE', 'CHECK')->orderBy('CONSTRAINT_NAME')->pluck('ENFORCED', 'CONSTRAINT_NAME')->all();
        $this->assertSame([
            'chk_activities_billing' => 'YES',
            'chk_activities_flags' => 'YES',
            'chk_activities_hourly_rate' => 'YES',
            'chk_activities_monthly_price' => 'YES',
            'chk_activities_name' => 'YES',
        ], $checks);
        $this->assertSame(['InnoDB', 'InnoDB'], DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->whereIn('TABLE_NAME', ['activities', 'identity_feature_provisions'])->orderBy('TABLE_NAME')->pluck('ENGINE')->all());
        $this->assertSame(0, DB::table('activities')->count());
        $this->assertSame(0, DB::table('identity_feature_provisions')->count());
        $this->assertSame(0, DB::table('permissions')->count());
    }

    public function test_actual_schema_exact_decimal_roundtrip_and_resource_shape(): void
    {
        $request = Request::create('/api/v1/activities', 'GET');
        foreach ([
            ['سباحة', true, false, '0.01', null, true],
            ['حضانة', false, true, null, '9999999999.99', false],
            ['رسم', true, true, '60.50', '100.00', true],
        ] as [$name, $hourly, $subscription, $rate, $price, $active]) {
            $activity = Activity::query()->create([
                'name' => $name, 'supports_hourly' => $hourly, 'supports_subscription' => $subscription,
                'hourly_rate' => $rate, 'monthly_price' => $price, 'is_active' => $active,
            ])->fresh();
            $this->assertSame([
                'id' => (int) $activity->id, 'name' => $name, 'supportsHourly' => $hourly, 'supportsSubscription' => $subscription,
                'hourlyRate' => $rate, 'monthlyPrice' => $price, 'isActive' => $active,
            ], (new ActivityResource($activity))->resolve($request));
        }
        $id = DB::table('activities')->insertGetId([
            'name' => 'Default active', 'supports_hourly' => true, 'supports_subscription' => false, 'hourly_rate' => '1.00',
        ]);
        $this->assertTrue(Activity::query()->findOrFail($id)->is_active);
        $this->assertSame(0, ActivityBillingMode::HOURLY->value);
        $this->assertSame(1, ActivityBillingMode::SUBSCRIPTION->value);
    }

    public function test_actual_schema_rejects_duplicate_and_invalid_billing_records(): void
    {
        $defaults = ['supports_hourly' => 1, 'supports_subscription' => 0, 'hourly_rate' => '60.00', 'monthly_price' => null, 'is_active' => 1];
        $insert = static fn (string $name, array $attributes = []) => DB::table('activities')->insertGetId(array_replace(['name' => $name], $defaults, $attributes));
        foreach ([
            ['Swimming', 'swimming'],
            ['الرسم', 'الـرسم'],
            ['كرة قدم', "كرة\u{00A0}قدم"],
            ['Café', "Cafe\u{0301}"],
        ] as [$name, $duplicate]) {
            $id = $insert($name, ['is_active' => 0]);
            $this->assertRejected(fn () => $insert($duplicate), 1062, 'uq_activities_name');
            $this->assertSame($name, DB::table('activities')->where('id', $id)->value('name'));
        }
        $insert('Cafe');
        $insert('الرَسم');
        $insert('كرة  قدم');
        foreach ([
            ['no-mode', ['supports_hourly' => 0, 'hourly_rate' => null], 3819, 'chk_activities_billing'],
            ['missing-hourly', ['hourly_rate' => null], 3819, 'chk_activities_hourly_rate'],
            ['zero-hourly', ['hourly_rate' => '0.00'], 3819, 'chk_activities_hourly_rate'],
            ['negative-hourly', ['hourly_rate' => '-0.01'], 3819, 'chk_activities_hourly_rate'],
            ['disabled-hourly', ['supports_hourly' => 0, 'supports_subscription' => 1, 'monthly_price' => '1.00'], 3819, 'chk_activities_hourly_rate'],
            ['missing-monthly', ['supports_subscription' => 1], 3819, 'chk_activities_monthly_price'],
            ['zero-monthly', ['supports_subscription' => 1, 'monthly_price' => '0.00'], 3819, 'chk_activities_monthly_price'],
            ['negative-monthly', ['supports_subscription' => 1, 'monthly_price' => '-0.01'], 3819, 'chk_activities_monthly_price'],
            ['disabled-monthly', ['monthly_price' => '1.00'], 3819, 'chk_activities_monthly_price'],
            ['invalid-active', ['is_active' => 2], 3819, 'chk_activities_flags'],
            ['invalid-hourly-flag', ['supports_hourly' => 2], 3819, null],
            ['invalid-subscription-flag', ['supports_subscription' => 2], 3819, null],
            ['empty-name', ['name' => ''], 3819, 'chk_activities_name'],
            ['spaces-name', ['name' => '   '], 3819, 'chk_activities_name'],
            ['long-name', ['name' => str_repeat('س', 256)], 1406, null],
            ['overflow-rate', ['hourly_rate' => '10000000000.00'], 1264, null],
            ['null-flag', ['supports_hourly' => null], 1048, null],
        ] as [$name, $fields, $code, $constraint]) {
            $this->assertRejected(fn () => $insert($name, $fields), $code, $constraint);
        }
        $first = $insert('Update one');
        $second = $insert('Update two');
        $this->assertRejected(fn () => DB::table('activities')->where('id', $second)->update(['name' => 'UPDATE ONE']), 1062, 'uq_activities_name');
        $this->assertRejected(fn () => DB::table('activities')->where('id', $first)->update(['hourly_rate' => null]), 3819, 'chk_activities_hourly_rate');
        $this->assertSame('Update two', DB::table('activities')->where('id', $second)->value('name'));
        $this->assertSame('60.00', DB::table('activities')->where('id', $first)->value('hourly_rate'));
        $this->assertSame(1, DB::table('activities')->where('id', $first)->update(['name' => 'UPDATE ONE']));
    }

    public function test_technical_marker_primary_key_preserves_exact_feature_key(): void
    {
        $record = ['feature_key' => 'activities.catalogue.v1', 'completed_at' => '2026-11-26 00:00:00'];
        DB::table('identity_feature_provisions')->insert($record);
        $this->assertSame($record, (array) DB::table('identity_feature_provisions')->first());
        $this->assertRejected(fn () => DB::table('identity_feature_provisions')->insert($record), 1062, 'PRIMARY');
        $this->assertSame(1, DB::table('identity_feature_provisions')->count());
    }

    private function assertRejected(\Closure $operation, int $mysqlCode, ?string $constraint): void
    {
        try {
            $operation();
        } catch (QueryException $failure) {
            $this->assertSame($mysqlCode, (int) ($failure->errorInfo[1] ?? 0));
            if ($constraint !== null) {
                $this->assertStringContainsString($constraint, (string) ($failure->errorInfo[2] ?? ''));
            }

            return;
        }
        $this->fail('MySQL accepted an invalid Activity fixture.');
    }
}
