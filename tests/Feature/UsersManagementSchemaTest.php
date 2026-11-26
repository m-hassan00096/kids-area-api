<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\Actions\BootstrapManagerAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\IdentityPermissions;
use Spatie\Permission\PermissionRegistrar;

class UsersManagementSchemaTest extends AuthenticationHttpTestCase
{
    public function test_avatar_reference_schema_is_nullable_unique_and_internal(): void
    {
        $column = DB::table('information_schema.COLUMNS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'users')
            ->where('COLUMN_NAME', 'avatar_key')
            ->sole(['COLUMN_TYPE as column_type', 'CHARACTER_SET_NAME as charset_name', 'COLLATION_NAME as collation_name', 'IS_NULLABLE as is_nullable', 'COLUMN_DEFAULT as default_value']);
        $this->assertSame('varchar(40)', $column->column_type);
        $this->assertSame('ascii', $column->charset_name);
        $this->assertSame('ascii_bin', $column->collation_name);
        $this->assertSame('YES', $column->is_nullable);
        $this->assertNull($column->default_value);

        $index = collect(DB::select('SHOW INDEX FROM users'))
            ->map(fn ($row) => array_change_key_case((array) $row))
            ->where('key_name', 'users_avatar_key_unique')->sole();
        $this->assertSame('avatar_key', $index['column_name']);
        $this->assertSame(0, (int) $index['non_unique']);
        $this->assertNull($index['sub_part']);

        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->assertSame(2, User::query()->whereNull('avatar_key')->count());
        $key = str_repeat('a', 32).'.png';
        DB::table('users')->where('id', $first->id)->update(['avatar_key' => $key]);
        try {
            DB::table('users')->where('id', $second->id)->update(['avatar_key' => $key]);
            $this->fail('Expected the reviewed unique avatar ownership constraint.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, (int) $exception->errorInfo[1]);
            $this->assertStringContainsString('users_avatar_key_unique', (string) $exception->errorInfo[2]);
        }
        $this->assertNull($second->fresh()->avatar_key);
        $first = $first->fresh();
        $this->assertSame($key, $first->avatar_key);
        $this->assertArrayNotHasKey('avatar_key', $first->toArray());
        $this->assertNotContains('avatar_key', $first->getFillable());
    }

    public function test_forward_avatar_migration_preserves_existing_founder_staff_roles_and_sessions(): void
    {
        // Parent has already verified the isolated test database before its guarded reset.
        // Recreate the exact pre-F03 schema using only the six existing migration files.
        $directory = 'Modules/Identity/database/migrations/';
        $baseline = array_map(fn ($file) => $directory.$file, [
            '2026_11_25_000001_create_users_table.php',
            '2026_11_25_000002_create_permission_tables.php',
            '2026_11_25_000003_enforce_single_staff_role.php',
            '2026_11_25_000004_add_auth_version_to_users.php',
            '2026_11_25_000005_create_sessions_table.php',
            '2026_11_25_000006_create_identity_login_throttles_table.php',
        ]);
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--path' => $baseline, '--force' => true]), Artisan::output());
        $this->assertFalse(Schema::hasColumn('users', 'avatar_key'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // This is the exact historical F02 schema, where Role aliases are still required.
        // Use a test-only legacy fixture; current production seeders target F04.
        $manager = Role::create(['name' => 'manager', 'guard_name' => 'web', 'alias_name' => 'مدير', 'is_active' => true]);
        Role::create(['name' => 'reception', 'guard_name' => 'web', 'alias_name' => 'استقبال', 'is_active' => true]);
        $permissions = collect();
        foreach (IdentityPermissions::CATALOGUE as $name => $alias) {
            $permissions->push(Permission::create(['name' => $name, 'guard_name' => 'web', 'alias_name' => $alias]));
        }
        $manager->syncPermissions($permissions);

        $founder = app(BootstrapManagerAction::class)->execute([
            'name' => 'Synthetic Founder',
            'username' => 'Fixture.Founder',
            'email' => null,
            'phone' => null,
            'password' => 'test-fixture-only-password',
            'password_confirmation' => 'test-fixture-only-password',
        ]);
        $staff = $this->user('Fixture.Existing', 'reception', ['phone' => '00012345678']);
        $this->assertSame(1, (int) $founder->id);
        $first = $this->browser($this->server());
        $second = $this->browser($this->server());
        $this->assertSame(200, $first->login($founder->username)->status);
        $this->assertSame(200, $second->login($staff->username)->status);

        $userColumns = Schema::getColumnListing('users');
        $before = $this->fingerprint('users', ['id'], $userColumns);
        $pivots = $this->fingerprint('model_has_roles', ['model_type', 'model_id', 'role_id']);
        $roles = $this->fingerprint('roles', ['id']);
        $grants = $this->fingerprint('role_has_permissions', ['role_id', 'permission_id']);
        $sessions = $this->fingerprint('sessions', ['id']);
        $this->assertSame(2, DB::table('sessions')->whereNotNull('auth_user_id')->count());

        $this->assertSame(0, Artisan::call('migrate', ['--path' => [$directory.'2026_11_25_000007_add_avatar_key_to_users.php'], '--force' => true]), Artisan::output());
        $this->assertTrue(Schema::hasColumn('roles', 'alias_name'));
        $this->assertSame([...$userColumns, 'avatar_key'], Schema::getColumnListing('users'));
        $this->assertSame(2, DB::table('users')->whereNull('avatar_key')->count());
        $this->assertSame($before, $this->fingerprint('users', ['id'], $userColumns));
        $this->assertSame($pivots, $this->fingerprint('model_has_roles', ['model_type', 'model_id', 'role_id']));
        $this->assertSame($roles, $this->fingerprint('roles', ['id']));
        $this->assertSame($grants, $this->fingerprint('role_has_permissions', ['role_id', 'permission_id']));
        $this->assertSame($sessions, $this->fingerprint('sessions', ['id']));
        $this->assertSame(200, $first->me()->status);
        $this->assertSame(200, $second->me()->status);
        $this->assertSame($founder->username, $first->me()->json()['data']['username']);
        $this->assertSame($staff->username, $second->me()->json()['data']['username']);
    }

    private function fingerprint(string $table, array $order, array $columns = ['*']): string
    {
        $query = DB::table($table);
        foreach ($order as $column) {
            $query->orderBy($column);
        }

        // Fingerprints avoid emitting password hashes, raw session contents or IDs in assertion output.
        return hash('sha256', json_encode($query->get($columns)->all(), JSON_THROW_ON_ERROR));
    }
}
