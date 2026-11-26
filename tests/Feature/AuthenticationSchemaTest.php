<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\IdentityPermissions;

class AuthenticationSchemaTest extends IdentityDatabaseTestCase
{
    protected function fixtureMigrationPaths(): array
    {
        if ($this->name() !== 'test_forward_authentication_migrations_preserve_existing_staff_and_roles') {
            return parent::fixtureMigrationPaths();
        }

        $directory = 'Modules/Identity/database/migrations/';

        return array_map(fn ($file) => $directory.$file, [
            '2026_11_25_000001_create_users_table.php',
            '2026_11_25_000002_create_permission_tables.php',
            '2026_11_25_000003_enforce_single_staff_role.php',
        ]);
    }

    public function test_forward_authentication_migrations_preserve_existing_staff_and_roles(): void
    {
        // Start from the exact F01 schema via the existing guarded fixture setup.
        // The forward-only Role alias migration must never be rolled back.
        $this->assertFalse(Schema::hasColumn('users', 'auth_version'));
        $this->assertFalse(Schema::hasColumn('users', 'avatar_key'));
        $this->assertFalse(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasColumn('roles', 'alias_name'));
        // Historical schema requires the old Role alias; do not use the F04 seeder.
        $manager = Role::create(['name' => 'manager', 'guard_name' => 'web', 'alias_name' => 'مدير', 'is_active' => true]);
        Role::create(['name' => 'reception', 'guard_name' => 'web', 'alias_name' => 'استقبال', 'is_active' => true]);
        $permissions = collect();
        foreach (IdentityPermissions::CATALOGUE as $name => $alias) {
            $permissions->push(Permission::create(['name' => $name, 'guard_name' => 'web', 'alias_name' => $alias]));
        }
        $manager->syncPermissions($permissions);
        $user = User::factory()->create(['username' => 'Existing.Staff', 'phone' => '00012345678']);
        $user->syncRoles([Role::findByName('manager', 'web')]);
        $before = $user->fresh();
        $hash = hash('sha256', $before->password);
        $roles = $before->roles()->pluck('roles.id')->all();
        $directory = 'Modules/Identity/database/migrations/';
        $forward = array_map(fn ($file) => $directory.$file, [
            '2026_11_25_000004_add_auth_version_to_users.php',
            '2026_11_25_000005_create_sessions_table.php',
            '2026_11_25_000006_create_identity_login_throttles_table.php',
            '2026_11_25_000007_add_avatar_key_to_users.php',
        ]);
        $this->assertSame(0, Artisan::call('migrate', ['--path' => $forward, '--force' => true]), Artisan::output());
        $this->assertTrue(Schema::hasColumn('roles', 'alias_name'));
        $this->assertTrue(Schema::hasColumn('users', 'avatar_key'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('identity_login_throttles'));
        $after = $user->fresh();
        $this->assertSame($before->id, $after->id);
        $this->assertSame($before->username, $after->username);
        $this->assertSame($before->phone, $after->phone);
        $this->assertSame($hash, hash('sha256', $after->password));
        $this->assertSame($roles, $after->roles()->pluck('roles.id')->all());
        $this->assertSame(1, $after->auth_version);
        $this->assertArrayNotHasKey('auth_version', $after->toArray());
        $this->assertNotContains('auth_version', $after->getFillable());
    }

    public function test_native_session_and_throttle_schema_constraints_and_indexes(): void
    {
        $id = str_repeat('A', 40);
        DB::table('sessions')->insert(['id' => $id, 'payload' => 'synthetic', 'last_activity' => time()]);
        $row = DB::table('sessions')->where('id', $id)->first();
        $this->assertNull($row->auth_user_id);
        $this->assertNull($row->auth_version);
        $this->assertNull($row->auth_last_activity);
        try {
            DB::table('sessions')->where('id', $id)->update(['auth_user_id' => 1]);
            $this->fail('Partial binding must violate the MySQL CHECK.');
        } catch (QueryException $exception) {
            $this->assertSame(3819, (int) $exception->errorInfo[1]);
        }
        $this->assertNull(DB::table('sessions')->where('id', $id)->value('auth_user_id'));
        $indexes = collect(DB::select('SHOW INDEX FROM sessions'))->map(fn ($index) => array_change_key_case((array) $index))->pluck('key_name')->all();
        $this->assertContains('PRIMARY', $indexes);
        $this->assertContains('sessions_auth_owner_index', $indexes);
        $this->assertContains('sessions_last_activity_index', $indexes);
        $indexes = collect(DB::select('SHOW INDEX FROM identity_login_throttles'))->map(fn ($index) => array_change_key_case((array) $index))->pluck('key_name')->all();
        $this->assertContains('PRIMARY', $indexes);
        $this->assertContains('identity_login_throttles_expires_at_ms_index', $indexes);

        $sessionPlan = DB::select('EXPLAIN SELECT id FROM sessions WHERE auth_user_id = 1');
        $this->assertStringContainsString('sessions_auth_owner_index', (string) $sessionPlan[0]->possible_keys);
        $expiryPlan = DB::select('EXPLAIN SELECT scope, key_digest FROM identity_login_throttles WHERE expires_at_ms <= 1 ORDER BY expires_at_ms LIMIT 100');
        $this->assertStringContainsString('identity_login_throttles_expires_at_ms_index', (string) $expiryPlan[0]->possible_keys);
    }

    public function test_mysql_ddl_matches_reviewed_authentication_schema(): void
    {
        foreach (['sessions', 'identity_login_throttles'] as $table) {
            $ddl = (array) DB::selectOne('SHOW CREATE TABLE '.$table);
            $create = strtolower($ddl['Create Table']);
            $this->assertStringContainsString('engine=innodb', $create);
            $this->assertStringNotContainsString('foreign key', $create);
        }

        $columns = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->whereIn('TABLE_NAME', ['users', 'sessions', 'identity_login_throttles'])
            ->get(['TABLE_NAME as table_name', 'COLUMN_NAME as column_name', 'COLUMN_TYPE as column_type', 'COLLATION_NAME as collation_name', 'IS_NULLABLE as is_nullable'])
            ->keyBy(fn ($column) => $column->table_name.'.'.$column->column_name);
        $this->assertSame('bigint unsigned', $columns['users.auth_version']->column_type);
        $this->assertSame('NO', $columns['users.auth_version']->is_nullable);
        $this->assertSame('varchar(40)', $columns['sessions.id']->column_type);
        $this->assertSame('ascii_bin', $columns['sessions.id']->collation_name);
        $this->assertSame('longtext', $columns['sessions.payload']->column_type);
        $this->assertSame('int unsigned', $columns['sessions.last_activity']->column_type);
        foreach (['user_id', 'auth_user_id', 'auth_version', 'auth_last_activity'] as $column) {
            $this->assertSame('bigint unsigned', $columns['sessions.'.$column]->column_type);
            $this->assertSame('YES', $columns['sessions.'.$column]->is_nullable);
        }
        $this->assertSame('tinyint unsigned', $columns['identity_login_throttles.scope']->column_type);
        $this->assertSame('char(64)', $columns['identity_login_throttles.key_digest']->column_type);
        $this->assertSame('ascii_bin', $columns['identity_login_throttles.key_digest']->collation_name);
        $this->assertSame('json', $columns['identity_login_throttles.failure_times']->column_type);
        $this->assertSame('bigint unsigned', $columns['identity_login_throttles.blocked_until_ms']->column_type);
        $this->assertSame('bigint unsigned', $columns['identity_login_throttles.expires_at_ms']->column_type);

        $primary = collect(DB::select('SHOW INDEX FROM identity_login_throttles'))->map(fn ($index) => array_change_key_case((array) $index))
            ->where('key_name', 'PRIMARY')->sortBy('seq_in_index')->pluck('column_name')->values()->all();
        $this->assertSame(['scope', 'key_digest'], $primary);
    }
}
