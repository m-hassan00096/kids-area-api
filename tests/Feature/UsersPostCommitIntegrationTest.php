<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\AvatarFixtures;

class UsersPostCommitIntegrationTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness;

    public function test_actual_self_disable_suppresses_touch_and_native_save_does_not_restore_authority(): void
    {
        $founder = $this->user('Fixture.Founder');
        $server = $this->server();
        $admin = $this->browser($server);
        $this->assertSame(200, $admin->login($founder->username)->status);
        foreach (['users-action-cache' => 200, 'users-action-final-save' => 500] as $mode => $status) {
            $actor = $this->user('Fixture.'.$mode);
            $a = $this->browser($server);
            $b = $this->browser($server);
            $this->assertSame(200, $a->login($actor->username)->status);
            $this->assertSame(200, $b->login($actor->username)->status);
            $oldId = $a->sessionId();
            $response = $a->request('PUT', '/api/v1/users/'.$actor->id, $this->fields($actor, false), [
                'headers' => ['X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Inspect' => '1'],
            ]);
            $this->assertSame($status, $response->status, $response->header('X-Test-Exception-Class') ?? '');
            $this->assertSame('committed', $response->header('X-Test-Users-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Users-Self-Revoked'));
            $this->assertSame('0', $response->header('X-Test-Users-Touches'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertFalse($actor->fresh()->is_active);
            $this->assertSame(2, $actor->fresh()->auth_version);
            $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $actor->id)->count());
            $this->assertNull(DB::table('sessions')->where('id', $oldId)->value('auth_user_id'));
            if ($status === 200) {
                $this->assertSame('1', $response->header('X-Test-Users-Cache-Warning'));
                $this->assertNotSame($oldId, $a->sessionId());
                $this->assertNotNull($this->binding($a));
                $this->assertNull($this->binding($a)->auth_user_id);
                $this->assertNull($this->binding($a)->auth_version);
                $this->assertNull($this->binding($a)->auth_last_activity);
            } else {
                // The native session store can fail after a committed DB write.
                // A safe operational error is not evidence of rollback.
                $this->assertSame('1', $response->header('X-Test-Users-Outcome-Reported'));
                $this->assertSame(['success' => false, 'message' => __('api.errors.internal'), 'errors' => []], $response->json());
            }
            $this->assertSame(401, $a->me()->status);
            $this->assertSame(401, $b->me()->status);
            $this->assertSame(200, $admin->me()->status);
        }
    }

    public function test_real_replacement_cleanup_cache_and_lost_commit_ack_keep_authoritative_bytes(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($founder->username)->status);
        $path = '/api/v1/users/'.$target->id;
        $fields = $this->fields($target, true, 'reception');
        $this->assertSame(200, $browser->request('POST', $path, options: $this->multipart($fields, AvatarFixtures::image('png')))->status);
        foreach (['users-action-cleanup' => 200, 'users-action-cache' => 200, 'users-action-commit-ack' => 503] as $mode => $status) {
            $before = $target->fresh();
            $old = $before->avatar_key;
            $oldBytes = file_get_contents($this->barrierDir.'/avatars/'.$old);
            $password = 'synthetic-reset-'.$mode;
            $response = $browser->request('POST', $path, options: $this->multipart($fields + [
                'password' => $password, 'passwordConfirmation' => $password,
            ], AvatarFixtures::image('png', 24, 12), $mode));
            $this->assertSame($status, $response->status, $response->header('X-Test-Exception-Class') ?? '');
            $this->assertSame($status === 503 ? 'unknown' : 'committed', $response->header('X-Test-Users-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            if ($mode === 'users-action-cleanup') {
                $this->assertSame('1', $response->header('X-Test-Avatar-Cleanup-Warning'));
            } elseif ($mode === 'users-action-cache') {
                $this->assertSame('1', $response->header('X-Test-Users-Cache-Warning'));
            } else {
                $this->assertSame('1', $response->header('X-Test-Actual-Commit-Count'));
                $this->assertSame('1', $response->header('X-Test-Users-Outcome-Reported'));
            }
            $after = $target->fresh();
            $this->assertSame($before->auth_version + 1, $after->auth_version);
            $this->assertTrue(Hash::check($password, $after->password));
            $this->assertNotSame($old, $after->avatar_key);
            $this->assertSame($oldBytes, file_get_contents($this->barrierDir.'/avatars/'.$old));
            $this->assertFileExists($this->barrierDir.'/avatars/'.$after->avatar_key);
            $image = $browser->request('GET', $path.'/avatar');
            $this->assertSame(200, $image->status);
            $this->assertSame(file_get_contents($this->barrierDir.'/avatars/'.$after->avatar_key), $image->body);
            $this->assertSame([24, 12], array_slice(getimagesizefromstring($image->body), 0, 2));
            $this->assertSame(200, $browser->me()->status);
        }
        $this->assertCount(4, glob($this->barrierDir.'/avatars/*'));
    }

    public function test_actual_delayed_native_insert_after_disable_is_anonymous_even_after_reenable(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $unrelated = $this->user('Fixture.Other', 'reception');
        $a = $this->browser($this->server());
        $admin = $this->browser($this->server());
        $other = $this->browser($admin->server);
        $this->assertSame(200, $a->login($target->username)->status);
        $this->assertSame(200, $admin->login($founder->username)->status);
        $this->assertSame(200, $other->login($unrelated->username)->status);
        $oldId = $a->sessionId();
        $path = '/api/v1/users/'.$target->id;
        [$first, $second] = $this->race(
            $a->prepare('GET', '/api/v1/auth/me', options: ['headers' => [
                'X-Identity-Test-Mode' => 'hold-late-insert', 'X-Identity-Test-Barrier' => 'first',
            ]]),
            $admin->prepare('PUT', $path, $this->fields($target, false, 'reception'), ['headers' => [
                'X-Identity-Test-Mode' => 'observe', 'X-Identity-Test-Barrier' => 'second',
            ]]),
            null,
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $row = DB::table('sessions')->where('id', $oldId)->first();
        $this->assertNotNull($row, 'Actual late native INSERT must be observed.');
        $this->assertNull($row->auth_user_id);
        $this->assertNull($row->auth_version);
        $this->assertNull($row->auth_last_activity);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertSame(200, $admin->request('PUT', $path, $this->fields($target, true, 'reception'))->status);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $other->me()->status);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $target->id)->count());
        $this->assertSame(200, $a->login($target->username)->status);
    }

    private function fields(User $target, bool $active, string $role = 'manager'): array
    {
        return ['name' => $target->name, 'username' => $target->username, 'email' => $target->email, 'phone' => $target->phone,
            'roleId' => Role::findByName($role, 'web')->id, 'isActive' => $active];
    }

    private function multipart(array $fields, string $bytes, ?string $mode = null): array
    {
        $fields['_method'] = 'PUT';
        $fields['isActive'] = $fields['isActive'] ? 'true' : 'false';
        $boundary = 'kids-postcommit-fixture';
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"avatar\"; filename=\"fixture.png\"\r\nContent-Type: image/png\r\n\r\n{$bytes}\r\n";
        $headers = ['Content-Type' => 'multipart/form-data; boundary='.$boundary, 'X-Identity-Test-Inspect' => '1'];
        if ($mode !== null) {
            $headers['X-Identity-Test-Mode'] = $mode;
        }

        return ['raw' => $body."--{$boundary}--\r\n", 'headers' => $headers];
    }
}
