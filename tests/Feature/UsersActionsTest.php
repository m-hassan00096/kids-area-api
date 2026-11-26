<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\Support\AvatarFixtures;

class UsersActionsTest extends AuthenticationHttpTestCase
{
    public function test_actual_create_requires_founder_and_rolls_back_staged_duplicate(): void
    {
        $founder = $this->user('Fixture.Founder');
        $collision = $this->user('Fixture.Collision', 'reception');
        $actor = $this->user('Fixture.Actor');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $fields = ['name' => 'Fixture New', 'username' => 'Fixture.New', 'roleId' => (string) Role::findByName('reception', 'web')->id,
            'password' => 'new-fixture-password', 'passwordConfirmation' => 'new-fixture-password'];
        $response = $browser->request('POST', '/api/v1/users', options: $this->multipart($fields, 'users-action-duplicate-create'));
        $this->assertSame(422, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        $this->assertArrayHasKey('username', $response->json()['errors']);
        $this->assertSame('Fixture.New', $collision->fresh()->username);
        $this->assertNull($collision->fresh()->avatar_key);
        $this->assertSame(3, DB::table('users')->count());
        $this->assertSame([], glob($this->barrierDir.'/avatars/*'));

        // Missing founder is a deliberately corrupted synthetic fixture.
        $founder->delete();
        $fields['username'] = 'Fixture.Another';
        $response = $browser->request('POST', '/api/v1/users', options: $this->multipart($fields));
        $this->assertSame(409, $response->status);
        $this->assertSame('IDENTITY_FOUNDER_REQUIRED', $response->json()['errors']['code']);
        $this->assertSame(2, DB::table('users')->count());
        $this->assertSame([], glob($this->barrierDir.'/avatars/*'));
        $this->assertSame(200, $browser->me()->status);
    }

    public function test_actual_combined_disable_reset_reenable_and_role_changes(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $other = $this->user('Fixture.Other', 'reception');
        $server = $this->server();
        $admin = $this->browser($server);
        $a = $this->browser($server);
        $b = $this->browser($server);
        $unrelated = $this->browser($server);
        foreach ([[$admin, $founder], [$a, $target], [$b, $target], [$unrelated, $other]] as [$browser, $user]) {
            $this->assertSame(200, $browser->login($user->username)->status);
        }
        $path = '/api/v1/users/'.$target->id;
        $data = $this->fields($target, 'manager');
        $this->assertSame(200, $admin->request('PUT', $path, $data)->status);
        $this->assertSame(1, $target->fresh()->auth_version);
        $this->assertSame($target->password, $target->fresh()->password);
        $this->assertSame(200, $a->me()->status);
        $this->assertSame(200, $a->request('GET', '/api/v1/users')->status);
        $this->assertSame(2, DB::table('sessions')->where('auth_user_id', $target->id)->count());

        $data['isActive'] = false;
        $response = $admin->request('PUT', $path, $data + ['password' => '  replacement  ', 'passwordConfirmation' => '  replacement  ']);
        $this->assertSame(200, $response->status);
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertTrue(Hash::check('  replacement  ', $target->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $target->id)->count());
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(401, $b->me()->status);
        $this->assertSame(200, $admin->me()->status);
        $this->assertSame(200, $unrelated->me()->status);
        $this->assertSame(200, $admin->request('PUT', $path, $data)->status);
        $this->assertSame(2, $target->fresh()->auth_version);
        $data['isActive'] = true;
        $this->assertSame(200, $admin->request('PUT', $path, $data)->status);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(401, $a->login($target->username)->status);
        $this->assertSame(200, $a->login($target->username, '  replacement  ')->status);
        $this->assertSame(200, $admin->request('PUT', $path, $this->fields($target, 'reception'))->status);
        $this->assertSame(200, $a->me()->status);
        $this->assertSame(403, $a->request('GET', '/api/v1/users')->status);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertSame(200, $unrelated->me()->status);
    }

    public function test_actual_last_admin_rollback_and_self_disable_known_commit(): void
    {
        $founder = $this->user('Fixture.Founder');
        $other = $this->user('Fixture.Other', 'reception');
        $server = $this->server();
        $admin = $this->browser($server);
        $this->assertSame(200, $admin->login($founder->username)->status);
        $oldId = $admin->sessionId();
        $fields = $this->fields($founder, 'reception');
        $fields['name'] = 'Must roll back';
        $response = $admin->request('PUT', '/api/v1/users/'.$founder->id, $fields);
        $this->assertSame(409, $response->status);
        $this->assertSame('IDENTITY_LAST_ADMIN', $response->json()['errors']['code']);
        $this->assertSame($founder->name, $founder->fresh()->name);
        $this->assertSame('manager', $founder->fresh()->roles()->sole()->name);
        $this->assertSame($oldId, $admin->sessionId());
        $this->assertSame(200, $admin->me()->status);

        $this->assertSame(200, $admin->request('PUT', '/api/v1/users/'.$other->id, $this->fields($other, 'manager'))->status);
        $a = $this->browser($server);
        $b = $this->browser($server);
        $this->assertSame(200, $a->login($other->username)->status);
        $this->assertSame(200, $b->login($other->username)->status);
        $old = $a->sessionId();
        $fields = $this->fields($other, 'manager');
        $fields['isActive'] = false;
        $this->assertSame(200, $a->request('PUT', '/api/v1/users/'.$other->id, $fields)->status);
        $this->assertNotSame($old, $a->sessionId());
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $other->id)->count());
        $this->assertFalse($other->fresh()->is_active);
        $this->assertSame(2, $other->fresh()->auth_version);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(401, $b->me()->status);
        $this->assertSame(200, $admin->me()->status);
    }

    public function test_actual_roleless_repair_and_direct_grant_anomaly_denied(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = User::factory()->create(['username' => 'Fixture.Roleless']);
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($founder->username)->status);
        $path = '/api/v1/users/'.$target->id;
        $this->assertSame(200, $browser->request('PUT', $path, $this->fields($target, 'reception'))->status);
        $this->assertSame('reception', $target->fresh()->roles()->sole()->name);
        $permission = Permission::findByName('identity.users.view', 'web');
        $target->givePermissionTo($permission);
        $fields = $this->fields($target, 'manager');
        $fields['name'] = 'Must roll back';
        $response = $browser->request('PUT', $path, $fields);
        $this->assertSame(409, $response->status);
        $this->assertSame('IDENTITY_USER_ROLE_ANOMALY', $response->json()['errors']['code']);
        $this->assertSame(409, $browser->request('DELETE', $path)->status);
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertSame('reception', $target->fresh()->roles()->sole()->name);
        $this->assertSame([$permission->id], $target->fresh()->permissions()->pluck('permissions.id')->all());
    }

    public function test_actual_delete_respects_founder_self_fk_and_target_only_cleanup(): void
    {
        $founder = $this->user('Fixture.Founder');
        $actor = $this->user('Fixture.Actor');
        $target = $this->user('Fixture.Target', 'reception');
        $other = $this->user('Fixture.Other', 'reception');
        $server = $this->server();
        $admin = $this->browser($server);
        $targetBrowser = $this->browser($server);
        $otherBrowser = $this->browser($server);
        $secondTargetBrowser = $this->browser($server);
        foreach ([[$admin, $actor], [$targetBrowser, $target], [$secondTargetBrowser, $target], [$otherBrowser, $other]] as [$browser, $user]) {
            $this->assertSame(200, $browser->login($user->username)->status);
        }
        $this->assertSame(403, $admin->request('DELETE', '/api/v1/users/'.$founder->id)->status);
        $this->assertSame(403, $admin->request('DELETE', '/api/v1/users/'.$actor->id)->status);
        $this->assertSame(404, $admin->request('DELETE', '/api/v1/users/99999')->status);
        foreach ([$target, $other] as $user) {
            $fields = $this->fields($user, 'reception');
            $fields['_method'] = 'PUT';
            $fields['isActive'] = 'true';
            $fields['roleId'] = (string) $fields['roleId'];
            $fields['email'] = '';
            $fields['phone'] = '';
            $response = $admin->request('POST', '/api/v1/users/'.$user->id, options: $this->multipart($fields));
            $this->assertSame(200, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        }
        $key = $target->fresh()->avatar_key;
        $otherKey = $other->fresh()->avatar_key;
        $this->assertNotNull($key);
        $this->assertNotNull($otherKey);
        $this->assertFileExists($this->barrierDir.'/avatars/'.$key);
        $oldBytes = file_get_contents($this->barrierDir.'/avatars/'.$key);
        $before = $target->fresh()->getAttributes();
        $roleCount = Role::count();
        $permissionCount = Permission::count();
        Schema::create('fixture_user_links', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'fixture_user_protected_fk')->references('id')->on('users')->restrictOnDelete();
        });
        try {
            DB::table('fixture_user_links')->insert(['user_id' => $target->id]);
            $path = '/api/v1/users/'.$target->id;
            // An unregistered constraint must not be translated to a known
            // business conflict or expose the native SQL/constraint message.
            $unknown = $admin->request('DELETE', $path, options: ['headers' => ['X-Identity-Test-Inspect' => '1']]);
            $this->assertSame(500, $unknown->status);
            $this->assertSame('rolled_back', $unknown->header('X-Test-Users-Outcome'));
            $this->assertSame('1', $unknown->header('X-Test-Users-Outcome-Reported'));
            $this->assertSame('1', $unknown->header('X-Test-Redacted'));
            $this->assertSame($before, $target->fresh()->getAttributes());
            $this->assertSame(2, DB::table('sessions')->where('auth_user_id', $target->id)->count());
            $this->assertSame($oldBytes, file_get_contents($this->barrierDir.'/avatars/'.$key));
            $response = $admin->request('DELETE', $path, options: ['headers' => [
                'X-Identity-Test-Mode' => 'users-action-protected-fk', 'X-Identity-Test-Inspect' => '1',
            ]]);
            $this->assertSame(409, $response->status, $response->header('X-Test-Exception-Class') ?? '');
            $this->assertSame('IDENTITY_USER_LINKED', $response->json()['errors']['code']);
            $this->assertNotNull($target->fresh());
            $this->assertSame($key, $target->fresh()->avatar_key);
            $this->assertFileExists($this->barrierDir.'/avatars/'.$key);
            $this->assertSame('reception', $target->fresh()->roles()->sole()->name);
            $this->assertSame('rolled_back', $response->header('X-Test-Users-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame($before, $target->fresh()->getAttributes());
            $this->assertSame($oldBytes, file_get_contents($this->barrierDir.'/avatars/'.$key));
            $this->assertSame(2, DB::table('sessions')->where('auth_user_id', $target->id)->count());
            $this->assertSame(200, $targetBrowser->me()->status);
            $this->assertSame(200, $secondTargetBrowser->me()->status);
            $this->assertSame(1, DB::table('fixture_user_links')->count());
        } finally {
            Schema::drop('fixture_user_links');
        }
        $response = $admin->request('DELETE', '/api/v1/users/'.$target->id);
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => __('identity.user_deleted'), 'data' => []], $response->json());
        $this->assertNull($target->fresh());
        $this->assertSame(0, DB::table('model_has_roles')->where('model_type', $target->getMorphClass())->where('model_id', $target->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $target->id)->count());
        $this->assertFileDoesNotExist($this->barrierDir.'/avatars/'.$key);
        $this->assertFileExists($this->barrierDir.'/avatars/'.$otherKey);
        $this->assertSame($roleCount, Role::count());
        $this->assertSame($permissionCount, Permission::count());
        $this->assertSame(401, $targetBrowser->me()->status);
        $this->assertSame(401, $secondTargetBrowser->me()->status);
        $this->assertSame(200, $otherBrowser->me()->status);
        $this->assertSame(200, $admin->me()->status);
    }

    private function fields(User $user, string $role): array
    {
        return ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'phone' => $user->phone,
            'roleId' => Role::findByName($role, 'web')->id, 'isActive' => true];
    }

    private function multipart(array $fields, ?string $mode = null): array
    {
        $boundary = 'kids-actions-fixture';
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"avatar\"; filename=\"fixture.png\"\r\nContent-Type: image/png\r\n\r\n".AvatarFixtures::image('png')."\r\n";
        $headers = ['Content-Type' => 'multipart/form-data; boundary='.$boundary];
        if ($mode !== null) {
            $headers['X-Identity-Test-Mode'] = $mode;
        }

        return ['raw' => $body."--{$boundary}--\r\n", 'headers' => $headers];
    }
}
