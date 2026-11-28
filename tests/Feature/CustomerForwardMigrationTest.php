<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Activities\Support\ActivityPermissions;
use Modules\Identity\Models\User;
use Modules\Identity\Support\IdentityPermissions;

class CustomerForwardMigrationTest extends IdentityDatabaseTestCase
{
    private const CUSTOMER_MIGRATION = 'Modules/People/database/migrations/2026_11_28_000011_create_customers_table.php';

    private const ORDERS = [
        'activities' => ['id'],
        'identity_feature_provisions' => ['feature_key'],
        'identity_login_throttles' => ['scope', 'key_digest'],
        'migrations' => ['id'],
        'model_has_permissions' => ['permission_id', 'model_type', 'model_id'],
        'model_has_roles' => ['role_id', 'model_type', 'model_id'],
        'permissions' => ['id'],
        'role_has_permissions' => ['role_id', 'permission_id'],
        'roles' => ['id'],
        'sessions' => ['id'],
        'users' => ['id'],
    ];

    protected function fixtureMigrationPaths(): array
    {
        // Historical ten-migration baseline; never use the live aggregate
        // catalogue or the current seeder to manufacture legacy expectations.
        return [
            'Modules/Identity/database/migrations/2026_11_25_000001_create_users_table.php',
            'Modules/Identity/database/migrations/2026_11_25_000002_create_permission_tables.php',
            'Modules/Identity/database/migrations/2026_11_25_000003_enforce_single_staff_role.php',
            'Modules/Identity/database/migrations/2026_11_25_000004_add_auth_version_to_users.php',
            'Modules/Identity/database/migrations/2026_11_25_000005_create_sessions_table.php',
            'Modules/Identity/database/migrations/2026_11_25_000006_create_identity_login_throttles_table.php',
            'Modules/Identity/database/migrations/2026_11_25_000007_add_avatar_key_to_users.php',
            'Modules/Identity/database/migrations/2026_11_26_000008_remove_alias_name_from_roles.php',
            'Modules/Identity/database/migrations/2026_11_26_000009_create_identity_feature_provisions_table.php',
            'Modules/Activities/database/migrations/2026_11_26_000010_create_activities_table.php',
        ];
    }

    public function test_customer_forward_migration_preserves_the_populated_ten_migration_baseline(): void
    {
        $this->assertSame('3306', (string) DB::connection()->getConfig('port'));
        $this->assertEmpty(DB::connection()->getConfig('unix_socket'));
        $this->assertSame(array_keys(self::ORDERS), $this->tables());
        $this->assertFalse(Schema::hasTable('customers'));
        $this->assertSame(
            array_map(static fn (string $path): string => basename($path, '.php'), $this->fixtureMigrationPaths()),
            DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
        );
        foreach (array_keys(self::ORDERS) as $table) {
            if ($table !== 'migrations') {
                $this->assertSame(0, DB::table($table)->count());
            }
        }

        $this->populateHistoricalFixture();
        $before = $this->snapshot();
        $this->assertCount(10, $before['rows']['migrations']);
        $this->assertCount(15, $before['rows']['permissions']);
        $this->assertCount(4, $before['rows']['roles']);
        $this->assertCount(4, $before['rows']['users']);
        $this->assertCount(2, $before['rows']['activities']);
        $this->assertCount(1, $before['rows']['identity_feature_provisions']);

        $runnerTemp = getenv('RUNNER_TEMP');
        $this->assertIsString($runnerTemp);
        $this->assertTrue(is_dir($runnerTemp) && is_writable($runnerTemp));
        $path = $runnerTemp.DIRECTORY_SEPARATOR.'customer-baseline-'.bin2hex(random_bytes(8)).'.json';
        $backup = json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $handle = fopen($path, 'xb');
        $this->assertIsResource($handle);

        try {
            try {
                $this->assertTrue(chmod($path, 0600));
                $this->assertSame(strlen($backup), fwrite($handle, $backup));
                $this->assertTrue(fflush($handle));
            } finally {
                fclose($handle);
            }
            $this->assertSame($backup, file_get_contents($path), 'Synthetic backup round-trip failed.');

            $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--path' => [self::CUSTOMER_MIGRATION]]));
            $after = $this->snapshot();
            $this->assertCount(11, $after['rows']['migrations']);
            $this->assertEquals($before['rows']['migrations'], array_slice($after['rows']['migrations'], 0, 10), 'Historical migration records changed.');
            $this->assertSame(basename(self::CUSTOMER_MIGRATION, '.php'), $after['rows']['migrations'][10]->migration);
            $normalizeSequence = static fn (array $ddl): array => array_map(
                static fn (string $value): string => preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $value),
                $ddl,
            );
            $this->assertSame($normalizeSequence($before['schema']['migrations']), $normalizeSequence($after['schema']['migrations']));

            // Assert every old table separately without dumping any row values,
            // credentials or session payloads into test failure diagnostics.
            foreach (array_keys(self::ORDERS) as $table) {
                if ($table === 'migrations') {
                    continue;
                }
                $this->assertTrue($before['schema'][$table] === $after['schema'][$table], 'Historical schema changed: '.$table);
                $this->assertTrue($before['rows'][$table] == $after['rows'][$table], 'Historical rows changed: '.$table);
            }
            $expectedTables = [...array_keys(self::ORDERS), 'customers'];
            sort($expectedTables, SORT_STRING);
            $this->assertSame($expectedTables, $this->tables());
            $this->assertSame(['id', 'name', 'mobile', 'is_active', 'created_at', 'updated_at'], Schema::getColumnListing('customers'));
            $this->assertSame(0, DB::table('customers')->count());
            $this->assertSame(15, DB::table('permissions')->count());
            $this->assertFalse(DB::table('permissions')->where('name', 'like', 'people.%')->exists());
            $this->assertSame(['activities.catalogue.v1'], DB::table('identity_feature_provisions')->pluck('feature_key')->all());
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--path' => [self::CUSTOMER_MIGRATION]]));
            $this->assertTrue($after == $this->snapshot(), 'Repeated forward migration changed the historical snapshot.');

            fwrite(STDOUT, 'CUSTOMER_FORWARD_MIGRATION '.json_encode([
                'mysql' => DB::selectOne('SELECT VERSION() AS version')->version,
                'baselineTablesPreserved' => array_keys(self::ORDERS),
                'baselineCounts' => array_map('count', $before['rows']),
                'oldMigrationRecordsPreserved' => 10,
                'newMigrationRecords' => 1,
                'newTables' => ['customers'],
                'customersEmpty' => true,
                'oldSchemaPreserved' => true,
                'oldRowsPreserved' => true,
                'customRoleAndAliasesPreserved' => true,
                'activityMarkerPreserved' => true,
                'backupRoundTrip' => true,
                'migrationRepeatNoOp' => true,
                'scope' => 'synthetic guarded fixture; no production data or new permission grants',
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        } finally {
            unlink($path);
        }
    }

    private function populateHistoricalFixture(): void
    {
        $now = '2026-11-28 08:00:00';
        $definitions = IdentityPermissions::CATALOGUE + ActivityPermissions::CATALOGUE;
        $this->assertCount(10, IdentityPermissions::CATALOGUE);
        $this->assertCount(5, ActivityPermissions::CATALOGUE);
        $this->assertCount(15, $definitions);
        $this->assertArrayNotHasKey('people.customers.show', $definitions);
        $permissions = [];
        foreach ($definitions as $name => $alias) {
            $permissions[$name] = DB::table('permissions')->insertGetId([
                'name' => $name,
                'guard_name' => 'web',
                'alias_name' => $name === 'identity.users.view' ? 'عرض تجريبي مخصص' : $alias,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roles = [];
        foreach (['manager' => true, 'reception' => true, 'Synthetic.Custom' => true, 'Synthetic.Disabled' => false] as $name => $active) {
            $roles[$name] = DB::table('roles')->insertGetId([
                'name' => $name, 'guard_name' => 'web', 'is_active' => $active,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $grants = [
            'manager' => array_values(array_diff(array_keys($definitions), ['identity.audit.view'])),
            'reception' => ['activities.index', 'identity.users.view'],
            'Synthetic.Custom' => ['activities.show', 'identity.roles.show'],
            'Synthetic.Disabled' => ['activities.index'],
        ];
        foreach ($grants as $name => $keys) {
            foreach ($keys as $key) {
                DB::table('role_has_permissions')->insert(['role_id' => $roles[$name], 'permission_id' => $permissions[$key]]);
            }
        }

        $users = [];
        foreach ($roles as $name => $roleId) {
            $users[$name] = DB::table('users')->insertGetId([
                'name' => 'Synthetic forward fixture', 'username' => 'Fixture.'.$name,
                'email' => null, 'phone' => null, 'password' => Hash::make('test-fixture-only-password'),
                'is_active' => $name !== 'Synthetic.Disabled', 'auth_version' => 3,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $users[$name],
            ]);
        }
        // The session payload is an opaque synthetic row for preservation only.
        // This test does not claim authentication/session behavior verification.
        DB::table('sessions')->insert([
            'id' => str_repeat('a', 40), 'user_id' => $users['manager'], 'ip_address' => '127.0.0.1',
            'user_agent' => 'Synthetic fixture', 'payload' => 'synthetic-opaque-payload',
            'last_activity' => 1795852800, 'auth_user_id' => $users['manager'],
            'auth_version' => 3, 'auth_last_activity' => 1795852800,
        ]);
        DB::table('identity_login_throttles')->insert([
            'scope' => 0, 'key_digest' => str_repeat('a', 64), 'failure_times' => '[1795852800000]',
            'blocked_until_ms' => null, 'expires_at_ms' => 1795852860000,
        ]);
        DB::table('activities')->insert([
            ['name' => 'نشاط تجريبي', 'supports_hourly' => 1, 'supports_subscription' => 0, 'hourly_rate' => '30.00', 'monthly_price' => null, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Synthetic inactive subscription', 'supports_hourly' => 0, 'supports_subscription' => 1, 'hourly_rate' => null, 'monthly_price' => '100.00', 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('identity_feature_provisions')->insert(['feature_key' => 'activities.catalogue.v1', 'completed_at' => $now]);
    }

    private function tables(): array
    {
        return DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->orderBy('TABLE_NAME')->pluck('TABLE_NAME')->all();
    }

    private function snapshot(): array
    {
        $rows = [];
        $schema = [];
        foreach (self::ORDERS as $table => $columns) {
            $query = DB::table($table);
            foreach ($columns as $column) {
                $query->orderBy($column);
            }
            $rows[$table] = $query->get()->all();
            $schema[$table] = (array) DB::selectOne('SHOW CREATE TABLE '.chr(96).$table.chr(96));
        }

        return ['rows' => $rows, 'schema' => $schema];
    }
}
