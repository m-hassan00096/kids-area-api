<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Tests\Support\AvatarFixtures;
use Tests\Support\UsersRaceFixtures;

class UsersActualFaultTest extends AuthenticationHttpTestCase
{
    use UsersRaceFixtures;

    public function test_real_create_and_update_stage_write_close_and_hash_fail_before_mutation(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $server = $this->server();
        $browser = $this->browser($server);
        $this->assertSame(200, $browser->login($founder->username)->status);
        $path = '/api/v1/users/'.$target->id;
        $fields = $this->fields($target) + ['_method' => 'PUT'];
        $this->assertSame(200, $browser->request('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png'), 'observe'))->status);
        $before = $target->fresh()->getAttributes();
        $key = $before['avatar_key'];
        $bytes = file_get_contents($this->barrierDir.'/avatars/'.$key);
        foreach (['fault-avatar-stage', 'fault-avatar-write', 'fault-avatar-close', 'fault-users-hash'] as $mode) {
            $response = $browser->request('POST', '/api/v1/users', options: $this->upload($this->createFields('Fixture.MustNotExist'), AvatarFixtures::image('png'), $mode));
            $this->assertSame(500, $response->status, $mode.' '.($response->header('X-Test-Exception-Class') ?? ''));
            $this->assertSame('1', $response->header('X-Test-Redacted'), $mode.' '.$server->diagnostic());
            $this->assertSame('1', $response->header('X-Test-Correlated-Report'), $mode);
            $this->assertSame(['success' => false, 'message' => __('api.errors.internal'), 'errors' => []], $response->json(), $mode);
            $update = array_replace($fields, ['name' => 'Must not commit', 'password' => 'synthetic-fault-reset', 'passwordConfirmation' => 'synthetic-fault-reset']);
            $response = $browser->request('POST', $path, options: $this->upload($update, AvatarFixtures::image('png', 24, 8), $mode));
            $this->assertSame(500, $response->status, $mode.' '.($response->header('X-Test-Exception-Class') ?? ''));
            $this->assertSame('1', $response->header('X-Test-Redacted'), $mode.' '.$server->diagnostic());
            $this->assertSame('1', $response->header('X-Test-Correlated-Report'), $mode);
            $this->assertSame(['success' => false, 'message' => __('api.errors.internal'), 'errors' => []], $response->json(), $mode);
            $this->assertSame($before, $target->fresh()->getAttributes());
            $this->assertSame($bytes, file_get_contents($this->barrierDir.'/avatars/'.$key));
            $this->assertCount(1, glob($this->barrierDir.'/avatars/*'));
            $this->assertSame(2, DB::table('users')->count());
            $this->assertSame(2, DB::table('model_has_roles')->count());
            $this->assertSame(200, $browser->me()->status);
        }
    }

    public function test_actual_reference_pivot_hash_and_session_write_faults_rollback_everything(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $server = $this->server();
        $admin = $this->browser($server);
        $a = $this->browser($server);
        $b = $this->browser($server);
        foreach ([[$admin, $founder], [$a, $target], [$b, $target]] as [$browser, $user]) {
            $this->assertSame(200, $browser->login($user->username)->status);
        }
        $path = '/api/v1/users/'.$target->id;
        $fields = $this->fields($target) + ['_method' => 'PUT'];
        $this->assertSame(200, $admin->request('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png'), 'observe'))->status);
        $before = $target->fresh()->getAttributes();
        $key = $before['avatar_key'];
        $bytes = file_get_contents($this->barrierDir.'/avatars/'.$key);
        $update = array_replace($fields, ['name' => 'Must not commit', 'roleId' => Role::findByName('manager', 'web')->id, 'isActive' => false,
            'password' => 'synthetic-fault-reset', 'passwordConfirmation' => 'synthetic-fault-reset']);
        foreach (['fault-users-reference', 'fault-users-pivot', 'fault-password', 'fault-revoke'] as $mode) {
            $response = $admin->request('POST', $path, options: $this->upload($update, AvatarFixtures::image('png', 24, 8), $mode));
            $this->assertSame(500, $response->status, $mode.' '.($response->header('X-Test-Exception-Class') ?? ''));
            $this->assertSame('rolled_back', $response->header('X-Test-Users-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Users-Outcome-Reported'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame($before, $target->fresh()->getAttributes());
            $this->assertSame('reception', $target->fresh()->roles()->sole()->name);
            $this->assertSame(2, DB::table('sessions')->where('auth_user_id', $target->id)->count());
            $this->assertSame($bytes, file_get_contents($this->barrierDir.'/avatars/'.$key));
            $this->assertCount(1, glob($this->barrierDir.'/avatars/*'));
            $this->assertSame(200, $a->me()->status);
            $this->assertSame(200, $b->me()->status);
        }
        $response = $admin->request('POST', '/api/v1/users', options: $this->upload($this->createFields('Fixture.PivotFault'), AvatarFixtures::image('png'), 'fault-users-pivot'));
        $this->assertSame(500, $response->status);
        $this->assertSame('rolled_back', $response->header('X-Test-Users-Outcome'));
        $this->assertSame(2, DB::table('users')->count());
        $this->assertCount(1, glob($this->barrierDir.'/avatars/*'));
    }

    public function test_real_lock_timeout_and_failed_restore_preserve_primary_failure_and_candidate(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($founder->username)->status);
        $path = '/api/v1/users/'.$target->id;
        $fields = $this->fields($target) + ['_method' => 'PUT'];
        $this->assertSame(200, $admin->request('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png'), 'observe'))->status);
        $before = $target->fresh()->getAttributes();
        DB::beginTransaction();
        try {
            DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->lockForUpdate()->first();
            $start = microtime(true);
            $response = $admin->request('POST', $path, options: $this->upload(array_replace($fields, ['name' => 'Must not commit']),
                AvatarFixtures::image('png', 24, 8), 'users-action-timeout-restore'));
            $elapsed = microtime(true) - $start;
            $this->assertGreaterThanOrEqual(4.5, $elapsed);
            $this->assertLessThan(20, $elapsed);
            $this->assertSame(503, $response->status);
            $this->assertSame('rolled_back', $response->header('X-Test-Users-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Users-Restore-Warning'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $target->fresh()->getAttributes());
        $this->assertFileExists($this->barrierDir.'/avatars/'.$before['avatar_key']);
        // Restore failed, so conservatively retain even the unreferenced
        // candidate; it will not be served by the user's avatar endpoint.
        $this->assertCount(2, glob($this->barrierDir.'/avatars/*'));
        $this->assertSame(200, $admin->me()->status);
        $this->assertSame(200, $admin->request('GET', $path.'/avatar')->status);
    }
}
