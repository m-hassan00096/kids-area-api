<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\Support\AvatarFixtures;

class UsersMutationValidationTest extends AuthenticationHttpTestCase
{
    public function test_actual_create_and_full_update_validate_and_save_explicit_fields(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Exact.Target', 'reception', ['email' => 'Case@Example.test', 'phone' => '00012345678']);
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($founder->username)->status);
        $role = Role::findByName('reception', 'web');
        $create = ['name' => '  Fixture new  ', 'username' => 'Exact.New', 'roleId' => $role->id,
            'password' => '        ', 'passwordConfirmation' => '        '];
        foreach (['name', 'username', 'roleId', 'password', 'passwordConfirmation'] as $field) {
            $bad = $create;
            unset($bad[$field]);
            $response = $browser->request('POST', '/api/v1/users', $bad);
            $this->assertSame(422, $response->status, $field);
            $this->assertArrayHasKey($field, $response->json()['errors']);
        }
        foreach ([
            ['username', 'Exact.Target'], ['email', 'case@example.test'], ['phone', '00012345678'],
            ['password', '1234567'], ['passwordConfirmation', '       x'], ['roleId', 99999],
        ] as [$field, $value]) {
            $response = $browser->request('POST', '/api/v1/users', array_replace($create, [$field => $value]));
            $this->assertSame(422, $response->status, $field);
            $this->assertArrayHasKey($field, $response->json()['errors']);
        }
        // Reject privileged or server-owned fields even on otherwise valid input.
        foreach ([
            'id' => $founder->id,
            'userId' => $founder->id,
            'isActive' => false,
            'auth_version' => 2,
            'authVersion' => 2,
            'roles' => [$role->id],
            'permissions' => ['identity.users.delete'],
            'avatarUrl' => '/storage/fixture.png',
            'createdAt' => '2026-01-01',
            'updatedAt' => '2026-01-01',
        ] as $field => $value) {
            $response = $browser->request('POST', '/api/v1/users', $create + [$field => $value]);
            $this->assertSame(422, $response->status, $field);
            $this->assertArrayHasKey('request', $response->json()['errors']);
            $this->assertSame(2, User::count());
        }
        $response = $browser->request('POST', '/api/v1/users', array_replace($create, ['roleId' => [$role->id]]));
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('roleId', $response->json()['errors']);
        $this->assertSame(2, User::count());
        $response = $browser->request('POST', '/api/v1/users', $create);
        $this->assertSame(201, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        $this->assertSame(['success' => true, 'message' => __('identity.user_created'), 'data' => []], $response->json());
        $created = User::where('username', 'Exact.New')->sole();
        $this->assertSame('Fixture new', $created->name);
        $this->assertTrue($created->is_active);
        $this->assertNull($created->phone);
        $this->assertNull($created->email);
        $this->assertNull($created->avatar_key);
        $this->assertSame(1, $created->auth_version);
        $this->assertTrue(Hash::check('        ', $created->password));
        $this->assertSame('argon2id', password_get_info($created->password)['algoName']);
        $this->assertSame([$role->id], $created->roles()->pluck('roles.id')->all());
        $this->assertSame(0, $created->permissions()->count());
        $this->assertSame(201, $browser->request('POST', '/api/v1/users', array_replace($create, ['username' => 'exact.target']))->status);
        $path = '/api/v1/users/'.$target->id;
        $update = ['name' => '  Fixture edit  ', 'username' => $target->username, 'email' => $target->email, 'phone' => $target->phone,
            'roleId' => $role->id, 'isActive' => true];
        $response = $browser->request('PUT', $path, $update);
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => __('identity.user_updated'), 'data' => []], $response->json());
        $this->assertSame('Fixture edit', $target->fresh()->name);
        $this->assertSame($target->password, $target->fresh()->password);
        $this->assertSame(1, $target->fresh()->auth_version);
        foreach (array_keys($update) as $field) {
            $bad = $update;
            unset($bad[$field]);
            $response = $browser->request('PUT', $path, $bad);
            $this->assertSame(422, $response->status, $field);
            $this->assertArrayHasKey($field, $response->json()['errors']);
        }
        $targetBrowser = $this->browser($browser->server);
        $this->assertSame(200, $targetBrowser->login($target->username)->status);
        $sessionId = $targetBrowser->sessionId();
        foreach ([
            [],
            ['password' => null, 'passwordConfirmation' => null],
            ['email' => '', 'phone' => null, 'password' => '', 'passwordConfirmation' => ''],
        ] as $extra) {
            $this->assertSame(200, $browser->request('PUT', $path, array_replace($update, $extra))->status);
            $this->assertSame($target->password, $target->fresh()->password);
            $this->assertSame(1, $target->fresh()->auth_version);
            $this->assertSame(200, $targetBrowser->me()->status);
            $this->assertSame($sessionId, $targetBrowser->sessionId());
            $this->assertSame(1, DB::table('sessions')->where('auth_user_id', $target->id)->count());
        }
        $this->assertNull($target->fresh()->email);
        $this->assertNull($target->fresh()->phone);

        $this->assertSame(200, $browser->request('PUT', $path, $update + ['password' => '  abcdef  ', 'passwordConfirmation' => '  abcdef  '])->status);
        $this->assertSame('argon2id', password_get_info($target->fresh()->password)['algoName']);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $target->id)->count());
        $this->assertSame(401, $targetBrowser->me()->status);
        $this->assertSame(200, $browser->request('PUT', $path, $update + ['removeAvatar' => true])->status);
        $response = $browser->request('PUT', $path, $update + ['passwordConfirmation' => 'orphan-fixture']);
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('passwordConfirmation', $response->json()['errors']);
        $this->assertSame(422, $browser->request('PUT', $path, $update + ['userId' => $founder->id])->status);
        $this->assertSame(422, $browser->request('PUT', $path, array_replace($update, ['username' => $founder->username]))->status);
        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame('Fixture edit', $target->fresh()->name);
        $this->assertTrue(Hash::check('  abcdef  ', $target->fresh()->password));
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertSame($target->email, $target->fresh()->email);
        $this->assertSame($target->phone, $target->fresh()->phone);
    }

    public function test_early_founder_self_password_and_inactive_role_rules(): void
    {
        $founder = $this->user('Fixture.Founder');
        $actor = $this->user('Fixture.Actor');
        $inactive = Role::create(['name' => 'fixture-inactive', 'guard_name' => 'web', 'is_active' => false]);
        $target = $this->user('Fixture.Target', $inactive->name);
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $data = ['name' => 'Fixture', 'username' => $target->username, 'email' => null, 'phone' => null, 'roleId' => $inactive->id, 'isActive' => true];
        $this->assertSame(403, $browser->request('PUT', '/api/v1/users/'.$founder->id, $data)->status);
        $self = array_replace($data, ['username' => $actor->username, 'roleId' => Role::findByName('manager', 'web')->id]);
        $this->assertSame(403, $browser->request('PUT', '/api/v1/users/'.$actor->id, $self + ['password' => '12345678', 'passwordConfirmation' => '12345678'])->status);
        $path = '/api/v1/users/'.$target->id;
        $this->assertSame(200, $browser->request('PUT', $path, $data)->status);
        $this->assertSame(200, $browser->request('PUT', $path, array_replace($data, ['isActive' => false]))->status);
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(2, $target->fresh()->auth_version);
        $response = $browser->request('PUT', $path, $data);
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('roleId', $response->json()['errors']);
        $otherRole = Role::create(['name' => 'fixture-other-inactive', 'guard_name' => 'web', 'is_active' => false]);
        $this->assertSame(422, $browser->request('PUT', $path, array_replace($data, ['roleId' => $otherRole->id, 'isActive' => false]))->status);
        $founderBrowser = $this->browser($this->server());
        $this->assertSame(200, $founderBrowser->login($founder->username)->status);
        $founderData = array_replace($self, ['username' => $founder->username, 'isActive' => false]);
        $this->assertSame(403, $founderBrowser->request('PUT', '/api/v1/users/'.$founder->id, $founderData)->status);
    }

    public function test_actual_native_avatar_create_update_replace_remove_and_invalid_preservation(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($founder->username)->status);
        $roleId = Role::findByName('reception', 'web')->id;
        $create = ['name' => 'Fixture', 'username' => 'Fixture.New', 'roleId' => (string) $roleId,
            'password' => '        ', 'passwordConfirmation' => '        '];
        $update = ['_method' => 'PUT', 'name' => 'Fixture', 'username' => $target->username, 'roleId' => (string) $roleId,
            'isActive' => 'true', 'email' => '', 'phone' => ''];
        foreach ([['/api/v1/users', $create, 201], ['/api/v1/users/'.$target->id, $update, 200]] as [$path, $fields, $status]) {
            $response = $browser->request('POST', $path, options: $this->multipart($fields, 'synthetic invalid image'));
            $this->assertSame(422, $response->status);
            $this->assertArrayHasKey('avatar', $response->json()['errors']);
            $response = $browser->request('POST', $path, options: $this->multipart($fields, AvatarFixtures::image('png')));
            $this->assertSame($status, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        }
        $created = User::where('username', 'Fixture.New')->sole();
        $old = $target->fresh()->avatar_key;
        $this->assertNotNull($old);
        $this->assertNotNull($created->avatar_key);
        $this->assertNotSame($created->avatar_key, $old);
        $this->assertFileExists($this->barrierDir.'/avatars/'.$old);
        $this->assertSame(200, $browser->request('GET', '/api/v1/users/'.$target->id.'/avatar')->status);
        $response = $browser->request('POST', '/api/v1/users/'.$target->id, options: $this->multipart($update, 'invalid replacement'));
        $this->assertSame(422, $response->status);
        $this->assertSame($old, $target->fresh()->avatar_key);
        $response = $browser->request('POST', '/api/v1/users/'.$target->id, options: $this->multipart($update, AvatarFixtures::image('png', 32, 16)));
        $this->assertSame(200, $response->status);
        $new = $target->fresh()->avatar_key;
        $this->assertNotSame($old, $new);
        $this->assertFileDoesNotExist($this->barrierDir.'/avatars/'.$old);
        $this->assertFileExists($this->barrierDir.'/avatars/'.$new);
        $ordinary = ['name' => 'Fixture', 'username' => $target->username, 'roleId' => $roleId, 'isActive' => true, 'email' => null, 'phone' => null];
        $this->assertSame(200, $browser->request('PUT', '/api/v1/users/'.$target->id, $ordinary)->status);
        $this->assertSame($new, $target->fresh()->avatar_key);
        $this->assertSame(200, $browser->request('PUT', '/api/v1/users/'.$target->id, $ordinary + ['removeAvatar' => true])->status);
        $this->assertNull($target->fresh()->avatar_key);
        $this->assertFileDoesNotExist($this->barrierDir.'/avatars/'.$new);
        $this->assertFileExists($this->barrierDir.'/avatars/'.$created->avatar_key);
        $this->assertSame(3, DB::table('users')->count());
    }

    private function multipart(array $fields, string $bytes): array
    {
        $boundary = 'kids-validation-fixture';
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"avatar\"; filename=\"fixture.png\"\r\nContent-Type: image/png\r\n\r\n{$bytes}\r\n";

        return ['raw' => $body."--{$boundary}--\r\n", 'headers' => ['Content-Type' => 'multipart/form-data; boundary='.$boundary]];
    }
}
