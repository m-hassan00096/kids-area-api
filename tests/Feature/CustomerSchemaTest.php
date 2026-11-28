<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\People\Enums\CustomerStatus;
use Modules\People\Models\Customer;
use Modules\People\Providers\PeopleServiceProvider;

class CustomerSchemaTest extends IdentityDatabaseTestCase
{
    public function test_people_module_and_exact_forward_customer_schema_are_discovered(): void
    {
        $this->assertInstanceOf(PeopleServiceProvider::class, app()->getProvider(PeopleServiceProvider::class));
        $this->assertSame(['id', 'name', 'mobile', 'is_active', 'created_at', 'updated_at'], Schema::getColumnListing('customers'));
        $this->assertSame(11, DB::table('migrations')->count());
        $this->assertTrue(DB::table('migrations')->where('migration', '2026_11_28_000011_create_customers_table')->exists());

        $columns = DB::table('information_schema.COLUMNS')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'customers')
            ->get(['COLUMN_NAME as name', 'COLUMN_TYPE as type', 'IS_NULLABLE as nullable', 'COLLATION_NAME as collation_name', 'COLUMN_DEFAULT as default_value', 'EXTRA as extra'])
            ->keyBy('name');
        foreach ([
            ['id', 'bigint unsigned', 'NO'],
            ['name', 'varchar(255)', 'NO'],
            ['mobile', 'varchar(11)', 'YES'],
            ['is_active', 'tinyint unsigned', 'NO'],
            ['created_at', 'timestamp', 'YES'],
            ['updated_at', 'timestamp', 'YES'],
        ] as [$name, $type, $nullable]) {
            $this->assertSame($type, $columns[$name]->type);
            $this->assertSame($nullable, $columns[$name]->nullable);
        }
        $this->assertSame('auto_increment', $columns['id']->extra);
        $this->assertSame('utf8mb4_unicode_ci', $columns['name']->collation_name);
        $this->assertSame('ascii_bin', $columns['mobile']->collation_name);
        $this->assertNull($columns['mobile']->default_value);
        $this->assertSame('1', (string) $columns['is_active']->default_value);

        $indexes = DB::table('information_schema.STATISTICS')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'customers')->orderBy('INDEX_NAME')->orderBy('SEQ_IN_INDEX')
            ->get(['INDEX_NAME as name', 'COLUMN_NAME as column_name', 'NON_UNIQUE as non_unique']);
        $this->assertSame([
            ['PRIMARY', 'id', 0],
            ['uq_customers_mobile', 'mobile', 0],
        ], $indexes->map(static fn (object $row): array => [$row->name, $row->column_name, (int) $row->non_unique])->all());
        $this->assertSame([
            'chk_customers_mobile' => 'YES',
            'chk_customers_name' => 'YES',
            'chk_customers_status' => 'YES',
        ], DB::table('information_schema.TABLE_CONSTRAINTS')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'customers')->where('CONSTRAINT_TYPE', 'CHECK')
            ->orderBy('CONSTRAINT_NAME')->pluck('ENFORCED', 'CONSTRAINT_NAME')->all());
        $table = DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'customers')->first(['ENGINE as engine', 'TABLE_COLLATION as collation_name']);
        $this->assertSame('InnoDB', $table->engine);
        $this->assertSame('utf8mb4_unicode_ci', $table->collation_name);
        $this->assertSame(0, DB::table('information_schema.KEY_COLUMN_USAGE')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'customers')->whereNotNull('REFERENCED_TABLE_NAME')->count());
        $this->assertSame(0, DB::table('customers')->count());
        $this->assertSame(0, DB::table('permissions')->count());
        $this->assertSame(0, DB::table('identity_feature_provisions')->count());
    }

    public function test_model_cast_default_fillable_fields_and_nullable_mobile_roundtrip(): void
    {
        $this->assertSame(0, CustomerStatus::INACTIVE->value);
        $this->assertSame(1, CustomerStatus::ACTIVE->value);
        $model = new Customer;
        $this->assertSame(['name', 'mobile'], $model->getFillable());
        $this->assertFalse($model->isFillable('is_active'));
        $this->assertFalse($model->isFillable('id'));
        $this->assertFalse($model->isFillable('created_at'));
        $this->assertFalse($model->isFillable('updated_at'));
        $this->assertSame(CustomerStatus::ACTIVE, $model->is_active);

        $first = Customer::query()->create(['name' => 'عميل تجريبي', 'mobile' => '00000000001']);
        $second = Customer::query()->create(['name' => 'عميل تجريبي', 'mobile' => null]);
        $third = Customer::query()->create(['name' => 'عميل تجريبي']);
        $this->assertSame(CustomerStatus::ACTIVE, $first->fresh()->is_active);
        $this->assertSame('00000000001', $first->fresh()->mobile);
        $this->assertNull($second->fresh()->mobile);
        $this->assertNull($third->fresh()->mobile);
        $this->assertSame(3, Customer::query()->where('name', 'عميل تجريبي')->count());
        $this->assertSame(2, Customer::query()->whereNull('mobile')->count());
        $this->assertNotNull($first->fresh()->created_at);
        $this->assertNotNull($first->fresh()->updated_at);
        $this->assertSame('UTC', $first->fresh()->created_at->getTimezone()->getName());

        $id = DB::table('customers')->insertGetId(['name' => 'Synthetic database default']);
        $this->assertSame(CustomerStatus::ACTIVE, Customer::query()->findOrFail($id)->is_active);
        $inactiveId = DB::table('customers')->insertGetId(['name' => 'Synthetic inactive', 'mobile' => '00000000002', 'is_active' => 0]);
        $inactive = Customer::query()->findOrFail($inactiveId);
        $this->assertSame(CustomerStatus::INACTIVE, $inactive->is_active);
        $this->assertTrue($inactive->update(['name' => 'Synthetic renamed']));
        $this->assertSame(CustomerStatus::INACTIVE, $inactive->fresh()->is_active);
        $this->assertSame('00000000002', $inactive->fresh()->mobile);
        $this->assertSame(0, (int) DB::table('customers')->where('id', $inactiveId)->value('is_active'));
    }

    public function test_actual_migration_enforces_unique_mobile_and_named_checks_on_inserts_and_updates(): void
    {
        $first = DB::table('customers')->insertGetId(['name' => 'Synthetic duplicate name', 'mobile' => '00000000001']);
        $second = DB::table('customers')->insertGetId(['name' => 'Synthetic duplicate name', 'mobile' => '00000000002', 'is_active' => 0]);
        $third = DB::table('customers')->insertGetId(['name' => 'Synthetic duplicate name', 'mobile' => null]);
        $fourth = DB::table('customers')->insertGetId(['name' => 'Synthetic duplicate name']);
        $before = DB::table('customers')->orderBy('id')->get()->all();

        $this->assertRejected(fn () => DB::table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '00000000001']), 1062, 'uq_customers_mobile');
        $this->assertRejected(fn () => DB::table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '00000000002']), 1062, 'uq_customers_mobile');
        $this->assertRejected(fn () => DB::table('customers')->where('id', $third)->update(['mobile' => '00000000002']), 1062, 'uq_customers_mobile');
        $this->assertRejected(fn () => DB::table('customers')->insert(['name' => 'Synthetic invalid', 'is_active' => 2]), 3819, 'chk_customers_status');
        $this->assertRejected(fn () => DB::table('customers')->where('id', $first)->update(['is_active' => 255]), 3819, 'chk_customers_status');
        $this->assertRejected(fn () => DB::table('customers')->insert(['name' => 'Synthetic invalid', 'is_active' => null]), 1048);
        $this->assertRejected(fn () => DB::table('customers')->insert(['name' => '   ']), 3819, 'chk_customers_name');
        $this->assertRejected(fn () => DB::table('customers')->where('id', $first)->update(['name' => '']), 3819, 'chk_customers_name');
        foreach (['', '123', '0000000000a', '00000 00000', "0000000000\n", '0000000000-'] as $mobile) {
            $this->assertRejected(fn () => DB::table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => $mobile]), 3819, 'chk_customers_mobile');
            $this->assertRejected(fn () => DB::table('customers')->where('id', $first)->update(['mobile' => $mobile]), 3819, 'chk_customers_mobile');
        }
        $this->assertRejected(fn () => DB::table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '000000000001']), 1406);
        $this->assertRejected(fn () => DB::table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '٠٠٠٠٠٠٠٠٠٠١']), [1366, 3854, 3988], state: 'HY000');
        $this->assertEquals($before, DB::table('customers')->orderBy('id')->get()->all());

        $this->assertSame(1, DB::table('customers')->where('id', $first)->update(['name' => 'Synthetic renamed', 'mobile' => '00000000001']));
        $this->assertSame(1, DB::table('customers')->where('id', $second)->update(['mobile' => null]));
        $this->assertSame(3, DB::table('customers')->whereNull('mobile')->count());
        $this->assertSame(1, DB::table('customers')->where('id', $fourth)->update(['mobile' => '00000000002']));
        $this->assertSame(4, DB::table('customers')->count());
    }

    private function assertRejected(Closure $operation, int|array $code, ?string $constraint = null, ?string $state = null): void
    {
        try {
            $operation();
        } catch (QueryException $failure) {
            $this->assertContains((int) ($failure->errorInfo[1] ?? 0), (array) $code);
            if ($constraint !== null) {
                $this->assertTrue(str_contains((string) ($failure->errorInfo[2] ?? ''), $constraint), 'Unexpected rejected Customer constraint.');
            }
            if ($state !== null) {
                $this->assertSame($state, (string) ($failure->errorInfo[0] ?? ''));
            }

            return;
        }

        $this->fail('MySQL accepted deliberately invalid synthetic Customer data.');
    }
}
