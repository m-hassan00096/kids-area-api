<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\LoginThrottle;
use Modules\Identity\Support\IdentityPermissions;

class AuthenticationBrowserTest extends AuthenticationHttpTestCase
{
    public function test_actual_cookie_login_regenerates_id_and_me_has_exact_safe_contract(): void
    {
        $user = $this->user('Case.Admin');
        $reception = $this->user('case.admin', 'reception', ['password' => Hash::make('different-synthetic-password')]);
        $server = $this->server();
        $browser = $this->browser($server);
        $old = clone $browser;
        $oldId = hash('sha256', $browser->sessionId());

        $response = $browser->login('Case.Admin', options: ['headers' => ['X-Identity-Test-Inspect' => '1']]);
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => '', 'data' => []], $response->json());
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertNotSame($oldId, hash('sha256', $browser->sessionId()));
        $this->assertSame(401, $old->me()->status);
        $binding = $this->binding($browser);
        $this->assertSame($user->id, (int) $binding->auth_user_id);
        $this->assertSame(1, (int) $binding->auth_version);
        $this->assertFalse(str_contains($binding->payload, 'login_web_'));
        $this->assertFalse(str_contains($binding->payload, 'test-fixture-only-password'));

        $keys = IdentityPermissions::keys();
        sort($keys, SORT_STRING);
        $this->assertSame([
            'success' => true,
            'message' => '',
            'data' => [
                'name' => $user->name,
                'username' => 'Case.Admin',
                'role' => ['id' => Role::findByName('manager', 'web')->id, 'name' => 'manager', 'aliasName' => 'مدير'],
                'permissions' => $keys,
            ],
        ], $browser->me()->json());

        $other = $this->browser($server);
        $this->assertSame(401, $other->login('CASE.Admin')->status);
        $this->assertSame(200, $other->login('case.admin', 'different-synthetic-password')->status);
        $this->assertSame($reception->username, $other->me()->json()['data']['username']);
        $this->assertSame([], $other->me()->json()['data']['permissions']);
    }

    public function test_login_denials_are_uniform_and_do_not_create_authority(): void
    {
        $this->user('Active.User');
        $this->user('Inactive.User', attributes: ['is_active' => false]);
        User::factory()->create(['username' => 'Roleless.User']);
        $bad = Role::create(['name' => 'disabled-role', 'guard_name' => 'web', 'alias_name' => 'Disabled', 'is_active' => false]);
        $staff = User::factory()->create(['username' => 'Disabled.Role']);
        $staff->syncRoles([$bad]);
        $direct = $this->user('Direct.Grant', 'reception');
        $direct->givePermissionTo(Permission::where('name', 'identity.users.view')->sole());
        $wrong = $this->user('Wrong.Guard');
        $otherGuard = Role::create(['name' => 'other-role', 'guard_name' => 'other', 'alias_name' => 'Other']);
        DB::table('model_has_roles')->where('model_id', $wrong->id)->update(['role_id' => $otherGuard->id]);
        $server = $this->server();
        $browser = $this->browser($server);
        $expected = ['success' => false, 'message' => __('identity.invalid_login'), 'errors' => []];
        foreach (['Unknown.User', 'Active.User', 'Inactive.User', 'Roleless.User', 'Disabled.Role', 'Direct.Grant', 'Wrong.Guard'] as $username) {
            $password = $username === 'Active.User' ? 'wrong-synthetic-password' : 'test-fixture-only-password';
            $response = $browser->login($username, $password);
            $this->assertSame(401, $response->status);
            $this->assertSame($expected, $response->json());
        }
        $this->assertSame(0, DB::table('sessions')->whereNotNull('auth_user_id')->count());

        $this->user('Space.Password', attributes: ['password' => Hash::make('        ')]);
        $this->assertSame(200, $browser->login('Space.Password', '        ')->status);
    }

    public function test_corrupted_multiple_roles_and_changed_role_or_direct_grants_fail_closed(): void
    {
        $staff = $this->user('Multi.Role');
        $server = $this->server();
        $browser = $this->browser($server);
        $this->assertSame(200, $browser->login('Multi.Role')->status);

        // Guarded disposable schema only; deliberately simulate a legacy anomaly.
        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->dropUnique('identity_single_role_unique');
        });
        $reception = Role::findByName('reception', 'web');
        try {
            DB::table('model_has_roles')->insert([
                'model_type' => $staff->getMorphClass(),
                'model_id' => $staff->id,
                'role_id' => $reception->id,
            ]);
            $this->assertSame(403, $browser->me()->status);
            $this->assertSame(401, $this->browser($server)->login('Multi.Role')->status);
        } finally {
            DB::table('model_has_roles')->where('model_id', $staff->id)
                ->where('model_type', $staff->getMorphClass())->where('role_id', $reception->id)->delete();
            Schema::table('model_has_roles', function (Blueprint $table): void {
                $table->unique(['model_type', 'model_id'], 'identity_single_role_unique');
            });
        }

        $role = Role::create(['name' => 'custom-role', 'guard_name' => 'web', 'alias_name' => 'Custom']);
        $this->user('Custom.User', 'custom-role');
        $roleBrowser = $this->browser($server);
        $this->assertSame(200, $roleBrowser->login('Custom.User')->status);
        $role->update(['is_active' => false]);
        $this->assertSame(403, $roleBrowser->me()->status);
        $this->assertSame(401, $roleBrowser->me()->status);

        $direct = $this->user('Direct.User', 'reception');
        $directBrowser = $this->browser($server);
        $this->assertSame(200, $directBrowser->login('Direct.User')->status);
        $permission = Permission::where('name', 'identity.users.view')->sole();
        $direct->givePermissionTo($permission);
        $this->assertSame(403, $directBrowser->me()->status);
        $direct->revokePermissionTo($permission);
        $this->assertSame(401, $directBrowser->me()->status);
    }

    public function test_actual_csrf_validation_exact_fields_origins_and_guest_conflict(): void
    {
        $this->user('Staff.Admin');
        $server = $this->server();
        $browser = $this->browser($server);

        foreach (['missing', 'bad'] as $csrf) {
            $this->assertSame(419, $browser->login('Staff.Admin', options: ['csrf' => $csrf])->status);
        }
        foreach ([
            ['Origin' => 'https://unapproved.invalid'],
            ['Origin' => null, 'Referer' => null],
            ['Referer' => 'https://unapproved.invalid/'],
            ['Authorization' => 'Bearer 1|synthetic-token'],
            ['Host' => 'unapproved.invalid'],
        ] as $headers) {
            $this->assertSame(403, $browser->login('Staff.Admin', options: ['headers' => $headers])->status);
        }

        $this->assertSame(422, $browser->login(' Staff.Admin')->status);
        $this->assertSame(422, $browser->login('Staff.Admin', '')->status);
        $this->assertSame(422, $browser->login('staff@example.test')->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/auth/login', ['username' => 'Staff.Admin', 'password' => ['invalid']])->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/auth/login', ['username' => 'Staff.Admin', 'password' => 'test-fixture-only-password', 'remember' => true])->status);
        $this->assertSame(400, $browser->request('POST', '/api/v1/auth/login', options: ['raw' => '{invalid'])->status);
        $this->assertSame(0, DB::table('identity_login_throttles')->count());
        $this->assertSame(0, DB::table('sessions')->whereNotNull('auth_user_id')->count());

        $before = DB::table('sessions')->count();
        $preflight = $this->browser($server, false)->request('OPTIONS', '/api/v1/auth/login', options: ['headers' => ['Access-Control-Request-Method' => 'POST']]);
        $this->assertSame(204, $preflight->status);
        $this->assertSame($server->origin, $preflight->header('Access-Control-Allow-Origin'));
        $this->assertSame('true', $preflight->header('Access-Control-Allow-Credentials'));
        $this->assertSame($before, DB::table('sessions')->count());

        $this->assertSame(200, $browser->login('Staff.Admin')->status);
        $this->assertSame(409, $browser->login('Staff.Admin')->status);
        $this->assertSame(200, $browser->me()->status);
        $this->assertSame(419, $browser->request('POST', '/api/v1/auth/logout', options: ['csrf' => 'missing'])->status);
        $this->assertSame(419, $browser->request('PUT', '/api/v1/profile/password', [
            'currentPassword' => 'test-fixture-only-password',
            'password' => 'new-synthetic-password',
            'passwordConfirmation' => 'new-synthetic-password',
        ], ['csrf' => 'bad'])->status);
    }

    public function test_qualifying_activity_idle_boundary_and_fresh_grants(): void
    {
        $staff = $this->user('Staff.Reception', 'reception');
        $server = $this->server();
        $active = $this->browser($server);
        $idle = $this->browser($server);
        $idle->timeMs = $active->timeMs;
        $this->assertSame(200, $active->login('Staff.Reception')->status);
        $this->assertSame(200, $idle->login('Staff.Reception')->status);
        $start = $active->timeMs;
        $activity = (int) $this->binding($idle)->auth_last_activity;

        $idle->timeMs = $start + 43199000;
        $this->assertSame(204, $idle->handshake()->status);
        $this->assertSame(422, $idle->request('PUT', '/api/v1/profile/password', ['currentPassword' => 'wrong-synthetic-password'])->status);
        $this->assertSame($activity, (int) $this->binding($idle)->auth_last_activity);
        $active->timeMs = $start + 43199000;
        $this->assertSame(200, $active->me()->status);
        $this->assertSame(intdiv($active->timeMs, 1000), (int) $this->binding($active)->auth_last_activity);
        $idle->timeMs = $start + 43200000;
        $this->assertSame(401, $idle->me()->status);

        $active->timeMs = $start + 43200000;
        $this->assertSame([], $active->me()->json()['data']['permissions']);
        Role::findByName('reception', 'web')->givePermissionTo(Permission::where('name', 'identity.users.view')->sole());
        $this->assertSame(['identity.users.view'], $active->me()->json()['data']['permissions']);
        $staff->update(['is_active' => false]);
        $this->assertSame(403, $active->me()->status);
        $this->assertSame(401, $active->me()->status);
    }

    public function test_logout_retires_only_current_device(): void
    {
        $user = $this->user('Staff.Admin');
        $server = $this->server();
        $one = $this->browser($server);
        $two = $this->browser($server);
        $this->assertSame(200, $one->login('Staff.Admin')->status);
        $this->assertSame(200, $two->login('Staff.Admin')->status);
        $old = clone $one;
        $oldCsrf = hash('sha256', $one->cookies['XSRF-TOKEN']);
        $this->assertSame(['success' => true, 'message' => '', 'data' => []], $one->request('POST', '/api/v1/auth/logout')->json());
        $this->assertNotSame($oldCsrf, hash('sha256', $one->cookies['XSRF-TOKEN']));
        $this->assertSame(401, $old->me()->status);
        $this->assertSame(200, $two->me()->status);
        $this->assertSame(1, DB::table('sessions')->where('auth_user_id', $user->id)->count());
        $this->assertSame(401, $one->request('POST', '/api/v1/auth/logout')->status);
    }

    public function test_secure_cookie_flags_and_untrusted_proxy_headers(): void
    {
        $secure = $this->server(secure: true);
        $response = $this->browser($secure, false)->handshake();
        $this->assertSame(204, $response->status);
        $sessionCookie = null;
        $csrfCookie = null;
        foreach ($response->headers['set-cookie'] ?? [] as $cookie) {
            if (str_starts_with($cookie, config('session.cookie').'=')) {
                $sessionCookie = strtolower($cookie);
            } elseif (str_starts_with($cookie, 'XSRF-TOKEN=')) {
                $csrfCookie = strtolower($cookie);
            }
        }
        $this->assertTrue(is_string($sessionCookie) && str_contains($sessionCookie, 'secure')
            && str_contains($sessionCookie, 'httponly') && str_contains($sessionCookie, 'samesite=lax'));
        $this->assertTrue(is_string($csrfCookie) && str_contains($csrfCookie, 'secure') && ! str_contains($csrfCookie, 'httponly'));

        $server = $this->server();
        $browser = $this->browser($server);
        $this->assertSame(401, $browser->login('Unknown.User', options: ['headers' => ['X-Forwarded-For' => '198.51.100.20']])->status);
        $digest = app(LoginThrottle::class)->digest(2, '127.0.0.1');
        $this->assertTrue(DB::table('identity_login_throttles')->where('scope', 2)->where('key_digest', $digest)->exists());
    }

    public function test_native_payload_cannot_authorize_missing_wrong_owner_or_stale_generation_marker(): void
    {
        $staff = $this->user('Staff.Admin');
        $other = $this->user('Other.Staff', 'reception');
        $server = $this->server();
        $unaffected = $this->browser($server);
        $this->assertSame(200, $unaffected->login('Other.Staff')->status);

        foreach ([
            ['auth_user_id' => null, 'auth_version' => null, 'auth_last_activity' => null],
            ['auth_user_id' => $other->id],
            ['auth_version' => 2],
        ] as $marker) {
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login('Staff.Admin')->status);
            $oldId = $browser->sessionId();
            DB::table('sessions')->where('id', $oldId)->update($marker);
            $this->assertSame(401, $browser->me()->status);
            $this->assertFalse(DB::table('sessions')->where('id', $oldId)->whereNotNull('auth_user_id')->exists());
            $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
            $this->assertSame(200, $unaffected->me()->status);
        }
    }

    public function test_logout_delete_failure_is_not_reported_as_success(): void
    {
        $user = $this->user('Staff.Admin');
        $server = $this->server();
        $one = $this->browser($server);
        $two = $this->browser($server);
        $this->assertSame(200, $one->login('Staff.Admin')->status);
        $this->assertSame(200, $two->login('Staff.Admin')->status);
        $response = $one->request('POST', '/api/v1/auth/logout', options: ['headers' => [
            'X-Identity-Test-Mode' => 'fault-logout',
            'X-Identity-Test-Inspect' => '1',
        ]]);
        $this->assertSame(500, $response->status);
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame(2, DB::table('sessions')->where('auth_user_id', $user->id)->count());
        $this->assertSame(200, $one->me()->status);
        $this->assertSame(200, $two->me()->status);
        $this->assertSame(200, $one->request('POST', '/api/v1/auth/logout')->status);
        $this->assertSame(401, $one->me()->status);
        $this->assertSame(200, $two->me()->status);
    }

    public function test_canonical_default_ports_and_cross_subdomain_browser_flow(): void
    {
        $this->user('Staff.Admin');
        $server = $this->server(splitOrigins: true);
        $browser = $this->browser($server, false);
        $preflight = $browser->request('OPTIONS', '/api/v1/auth/login', options: ['headers' => [
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-xsrf-token',
        ]]);
        $this->assertSame(204, $preflight->status);
        $this->assertSame($server->origin, $preflight->header('Access-Control-Allow-Origin'));
        $this->assertSame('true', $preflight->header('Access-Control-Allow-Credentials'));
        $this->assertSame(0, DB::table('sessions')->count());
        $handshake = $browser->handshake();
        $this->assertSame(204, $handshake->status);
        $this->assertSame($server->origin, $handshake->header('Access-Control-Allow-Origin'));
        $this->assertTrue(collect($handshake->headers['set-cookie'] ?? [])->contains(fn ($cookie) => str_contains(strtolower($cookie), 'domain=.kids.example.test') || str_contains(strtolower($cookie), 'domain=kids.example.test')));
        $this->assertSame(403, $browser->login('Staff.Admin', options: ['headers' => ['Origin' => 'https://unapproved.kids.example.test']])->status);
        $this->assertSame(403, $browser->login('Staff.Admin', options: ['headers' => ['Referer' => 'https://api.kids.example.test/']])->status);
        $login = $browser->login('Staff.Admin', options: ['headers' => ['Referer' => $server->origin.':443/login']]);
        $this->assertSame(200, $login->status);
        $this->assertSame($server->origin, $login->header('Access-Control-Allow-Origin'));
        $this->assertSame(200, $browser->me(['headers' => ['Origin' => null, 'Referer' => $server->origin.'/dashboard']])->status);
    }

    public function test_old_csrf_tokens_are_rejected_after_each_rotation_and_writes_require_csrf(): void
    {
        $user = $this->user('Staff.Admin');
        $browser = $this->browser($this->server());
        $initial = rawurldecode($browser->cookies['XSRF-TOKEN']);
        foreach (['missing', 'bad'] as $csrf) {
            $this->assertSame(419, $browser->login('Staff.Admin', options: ['csrf' => $csrf])->status);
        }
        $this->assertSame(0, DB::table('identity_login_throttles')->count());
        $this->assertSame(200, $browser->login('Staff.Admin')->status);
        $this->assertSame(419, $browser->request('POST', '/api/v1/auth/logout', options: ['headers' => ['X-XSRF-TOKEN' => $initial]])->status);
        $change = ['currentPassword' => 'test-fixture-only-password', 'password' => 'new-synthetic-password', 'passwordConfirmation' => 'new-synthetic-password'];
        foreach (['missing', 'bad'] as $csrf) {
            $this->assertSame(419, $browser->request('POST', '/api/v1/auth/logout', options: ['csrf' => $csrf])->status);
            $this->assertSame(419, $browser->request('PUT', '/api/v1/profile/password', $change, ['csrf' => $csrf])->status);
        }
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->assertSame(200, $browser->me()->status);

        $beforeLogout = rawurldecode($browser->cookies['XSRF-TOKEN']);
        $this->assertSame(200, $browser->request('POST', '/api/v1/auth/logout')->status);
        $this->assertSame(419, $browser->login('Staff.Admin', options: ['headers' => ['X-XSRF-TOKEN' => $beforeLogout]])->status);
        $this->assertSame(200, $browser->login('Staff.Admin')->status);
        $beforeChange = rawurldecode($browser->cookies['XSRF-TOKEN']);
        $this->assertSame(200, $browser->request('PUT', '/api/v1/profile/password', $change)->status);
        $this->assertSame(419, $browser->login('Staff.Admin', 'new-synthetic-password', ['headers' => ['X-XSRF-TOKEN' => $beforeChange]])->status);
        $this->assertSame(2, $user->fresh()->auth_version);
        $this->assertSame(200, $browser->login('Staff.Admin', 'new-synthetic-password')->status);
    }
}
