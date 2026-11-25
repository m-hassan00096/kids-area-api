<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;

class AuthenticationSchemaTest extends IdentityDatabaseTestCase
{
    public function test_forward_authentication_migrations_preserve_existing_staff_and_roles(): void
    {
        // Only the guarded disposable MySQL schema is rolled back in this test.
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 3, '--force' => true]), Artisan::output());
        $this->assertFalse(Schema::hasColumn('users', 'auth_version'));
        $this->assertFalse(Schema::hasTable('sessions'));
        app(SeedIdentityCatalogueAction::class)->execute();
        $user = User::factory()->create(['username' => 'Existing.Staff', 'phone' => '00012345678']);
        $user->syncRoles([Role::findByName('manager', 'web')]);
        $before = $user->fresh();
        $hash = hash('sha256', $before->password);
        $roles = $before->roles()->pluck('roles.id')->all();
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
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
}
