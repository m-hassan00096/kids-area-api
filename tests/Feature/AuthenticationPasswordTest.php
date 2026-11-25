<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthenticationPasswordTest extends AuthenticationHttpTestCase
{
    public function test_founder_self_change_revokes_all_devices_and_preserves_unaffected_user(): void
    {
        $founder = $this->user('Founder.Admin');
        $other = $this->user('Other.Staff', 'reception');
        $this->assertSame(1, $founder->id);
        $server = $this->server();
        $one = $this->browser($server);
        $two = $this->browser($server);
        $unaffected = $this->browser($server);
        $this->assertSame(200, $one->login('Founder.Admin')->status);
        $this->assertSame(200, $two->login('Founder.Admin')->status);
        $this->assertSame(200, $unaffected->login('Other.Staff')->status);
        $old = clone $one;
        $oldHash = hash('sha256', $founder->password);
        $new = str_repeat('p', 72).'synthetic-new-tail';
        $valid = ['currentPassword' => 'test-fixture-only-password', 'password' => $new, 'passwordConfirmation' => $new];

        foreach ([
            ['currentPassword' => 'wrong-synthetic-password'] + $valid,
            ['passwordConfirmation' => 'mismatch-synthetic'] + $valid,
            ['password' => 'short', 'passwordConfirmation' => 'short'] + $valid,
            ['userId' => $other->id] + $valid,
            ['currentPassword' => ['invalid']] + $valid,
        ] as $invalid) {
            $response = $one->request('PUT', '/api/v1/profile/password', $invalid);
            $this->assertSame(422, $response->status);
            $this->assertFalse($response->json()['success']);
            $this->assertSame($oldHash, hash('sha256', $founder->fresh()->password));
            $this->assertSame(1, $founder->fresh()->auth_version);
            $this->assertSame(2, DB::table('sessions')->where('auth_user_id', $founder->id)->count());
        }
        $wrong = $one->request('PUT', '/api/v1/profile/password', ['currentPassword' => 'wrong-synthetic-password'] + $valid);
        $this->assertArrayHasKey('currentPassword', $wrong->json()['errors']);
        $response = $one->request('PUT', '/api/v1/profile/password', $valid, ['headers' => ['X-Identity-Test-Inspect' => '1']]);
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => __('identity.password_changed'), 'data' => []], $response->json());
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame(2, $founder->fresh()->auth_version);
        $this->assertTrue(Hash::check($new, $founder->fresh()->password));
        $this->assertFalse(Hash::check(str_repeat('p', 72).'different-tail', $founder->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $founder->id)->count());
        $this->assertSame(401, $old->me()->status);
        $this->assertSame(401, $one->me()->status);
        $this->assertSame(401, $two->me()->status);
        $this->assertSame(200, $unaffected->me()->status);

        $this->assertSame(204, $one->handshake()->status);
        $this->assertSame(401, $one->login('Founder.Admin')->status);
        $this->assertSame(200, $one->login('Founder.Admin', $new)->status);
    }

    public function test_faults_roll_back_credentials_and_sessions_and_redact_logs(): void
    {
        $user = $this->user('Staff.Admin');
        $server = $this->server();
        $one = $this->browser($server);
        $two = $this->browser($server);
        $this->assertSame(200, $one->login('Staff.Admin')->status);
        $this->assertSame(200, $two->login('Staff.Admin')->status);
        $before = hash('sha256', $user->password);
        $valid = [
            'currentPassword' => 'test-fixture-only-password',
            'password' => 'new-synthetic-password',
            'passwordConfirmation' => 'new-synthetic-password',
        ];
        foreach (['fault-password', 'fault-revoke'] as $mode) {
            $response = $one->request('PUT', '/api/v1/profile/password', $valid, [
                'headers' => ['X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Inspect' => '1', 'Accept' => 'text/html'],
            ]);
            $this->assertSame(500, $response->status);
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame('1', $response->header('X-Test-Safe-Report'));
            $this->assertSame(['success' => false, 'message' => __('api.errors.internal'), 'errors' => []], $response->json());
            $this->assertSame($before, hash('sha256', $user->fresh()->password));
            $this->assertSame(1, $user->fresh()->auth_version);
            $this->assertSame(2, DB::table('sessions')->where('auth_user_id', $user->id)->count());
            $this->assertSame(200, $one->me()->status);
            $this->assertSame(200, $two->me()->status);
        }
    }

    public function test_login_persistence_failure_cannot_establish_authenticated_marker(): void
    {
        $user = $this->user('Staff.Admin');
        $server = $this->server();
        foreach (['fault-native-insert', 'fault-marker', 'fault-report'] as $mode) {
            $browser = $this->browser($server);
            $response = $browser->login('Staff.Admin', options: ['headers' => [
                'X-Identity-Test-Mode' => $mode,
                'X-Identity-Test-Inspect' => '1',
            ]]);
            $this->assertSame(500, $response->status);
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame('1', $response->header('X-Test-Safe-Report'));
            $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $user->id)->count());
            $this->assertSame(401, $browser->me()->status);
        }
    }
}
