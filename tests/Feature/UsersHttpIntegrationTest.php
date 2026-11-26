<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\IdentityPermissions;
use Tests\Support\AvatarFixtures;

class UsersHttpIntegrationTest extends AuthenticationHttpTestCase
{
    public function test_actual_independent_write_capabilities_without_controller_probe(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $server = $this->server();
        foreach (['create', 'update', 'delete'] as $capability) {
            $role = Role::create(['name' => 'fixture-'.$capability, 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::findByName('identity.users.'.$capability, 'web'));
            $staff = $this->user('Fixture.'.$capability, $role->name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($staff->username)->status);
            $path = '/api/v1/users/'.$target->id;
            $create = $this->createFields('Fixture.New.'.$capability);
            $update = $this->fields($target, 'reception');
            $update['name'] = 'Fixture Updated';
            foreach ([
                ['GET', $path, null, false, 403],
                ['POST', '/api/v1/users', $create, $capability === 'create', $capability === 'create' ? 201 : 403],
                ['PUT', $path, $update, $capability === 'update', $capability === 'update' ? 200 : 403],
                ['DELETE', $path, null, $capability === 'delete', $capability === 'delete' ? 200 : 403],
            ] as [$method, $uri, $body, $allowed, $status]) {
                $response = $browser->request($method, $uri, $body);
                $this->assertSame($status, $response->status, $capability.' '.$method.' '.$uri);
                $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
                if (! $allowed) {
                    $this->assertSame('0', $response->header('X-Test-Users-Target-Reads'));
                }
            }
            if ($capability === 'create') {
                $this->assertNotNull(User::where('username', $create['username'])->first());
                $this->assertSame($target->name, $target->fresh()->name);
            } elseif ($capability === 'update') {
                $this->assertSame('Fixture Updated', $target->fresh()->name);
            } else {
                $this->assertNull($target->fresh());
            }
            $response = $browser->request('PUT', '/api/v1/users/99999', $update);
            $this->assertSame($capability === 'update' ? 404 : 403, $response->status);
            if ($capability !== 'update') {
                $this->assertSame('0', $response->header('X-Test-Users-Target-Reads'));
            }
            $this->assertSame(200, $browser->me()->status);
        }
        $manager = $this->browser($server);
        $this->assertSame(200, $manager->login($founder->username)->status);
        Role::findByName('manager', 'web')->revokePermissionTo('identity.users.create');
        $this->assertSame(403, $manager->request('POST', '/api/v1/users', $this->createFields('Fixture.NoBypass'))->status);
        $this->assertNull(User::where('username', 'Fixture.NoBypass')->first());
        $this->assertSame(200, $manager->me()->status);
    }

    public function test_actual_json_and_multipart_writes_require_native_csrf_and_allowed_origin(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $server = $this->server(splitOrigins: true);
        $admin = $this->browser($server);
        $guest = $this->browser($server);
        $this->assertSame(200, $admin->login($founder->username)->status);
        $path = '/api/v1/users/'.$target->id;
        $create = $this->createFields('Fixture.New');
        $update = array_replace($this->fields($target, 'reception'), ['name' => 'Fixture Changed']);
        $multipart = $this->multipart($update + ['_method' => 'PUT'], [['avatar', 'fixture.png', AvatarFixtures::image('png')]]);
        foreach ([
            ['POST', '/api/v1/users', $create, []],
            ['PUT', $path, $update, []],
            ['POST', $path, null, $multipart],
            ['DELETE', $path, null, []],
        ] as [$method, $uri, $body, $options]) {
            foreach (['missing', 'bad'] as $csrf) {
                $response = $admin->request($method, $uri, $body, $options + ['csrf' => $csrf]);
                $this->assertSame(419, $response->status);
                $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
            }
            $badOrigin = $options;
            $badOrigin['headers']['Origin'] = 'https://unapproved.invalid';
            $this->assertSame(403, $admin->request($method, $uri, $body, $badOrigin)->status);
            $this->assertSame(401, $guest->request($method, $uri, $body, $options)->status);
            $this->assertSame($target->name, $target->fresh()->name);
            $this->assertNull($target->fresh()->avatar_key);
            $this->assertSame(2, User::count());
        }
        $this->assertSame(201, $admin->request('POST', '/api/v1/users', $create)->status);
        $this->assertSame(200, $admin->request('POST', $path, options: $multipart)->status);
        $this->assertSame('Fixture Changed', $target->fresh()->name);
        $this->assertNotNull($target->fresh()->avatar_key);
        $this->assertSame(200, $admin->request('DELETE', $path)->status);
        $this->assertNull($target->fresh());
    }

    public function test_actual_upload_formats_limits_and_invalid_replacement_preserve_bytes(): void
    {
        $founder = $this->user('Fixture.Founder');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($founder->username)->status);
        $create = $this->createFields('Fixture.Images');
        $png = AvatarFixtures::image('png');
        $exact = AvatarFixtures::pngOfSize(2097152);
        $response = $browser->request('POST', '/api/v1/users', options: $this->multipart($create, [['avatar', 'fixture.png', $exact]]));
        $this->assertSame(201, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        $target = User::where('username', $create['username'])->sole();
        $path = '/api/v1/users/'.$target->id;
        $update = $this->fields($target, 'reception') + ['_method' => 'PUT'];
        foreach (['jpg' => 'image/jpeg', 'webp' => 'image/webp'] as $extension => $mime) {
            $old = $target->fresh()->avatar_key;
            $response = $browser->request('POST', $path, options: $this->multipart($update, [['avatar', 'fixture.'.$extension, AvatarFixtures::image($extension, 32, 16)]]));
            $this->assertSame(200, $response->status);
            $new = $target->fresh()->avatar_key;
            $this->assertNotSame($old, $new);
            $this->assertFileDoesNotExist($this->barrierDir.'/avatars/'.$old);
            $read = $browser->request('GET', $path.'/avatar');
            $this->assertSame(200, $read->status);
            $this->assertSame($mime, $read->header('Content-Type'));
            $this->assertSame([32, 16], array_slice(getimagesizefromstring($read->body), 0, 2));
        }
        $key = $target->fresh()->avatar_key;
        $bytes = file_get_contents($this->barrierDir.'/avatars/'.$key);
        foreach ([
            [['avatar', 'fixture.png', AvatarFixtures::pngOfSize(2097153)]],
            [['avatar', 'fixture.jpg', $png]],
            [['avatar', 'fixture.png', $png.'trailing']],
            [['avatar[]', 'fixture.png', $png]],
            [['avatar', 'fixture.png', $png], ['extra', 'fixture.png', $png]],
            [['avatar', 'fixture.svg', '<svg></svg>']],
        ] as $parts) {
            $response = $browser->request('POST', $path, options: $this->multipart($update, $parts));
            $this->assertSame(422, $response->status);
            $this->assertArrayHasKey('avatar', $response->json()['errors']);
            $this->assertSame($key, $target->fresh()->avatar_key);
            $this->assertSame($bytes, file_get_contents($this->barrierDir.'/avatars/'.$key));
        }
        foreach (['/storage/fixture.png', 'data:image/png;base64,c3ludGhldGlj'] as $value) {
            $this->assertSame(422, $browser->request('PUT', $path, $this->fields($target, 'reception') + ['avatar' => $value])->status);
        }
        $this->assertSame(422, $browser->request('POST', $path, options: $this->multipart($update + ['removeAvatar' => true], [['avatar', 'fixture.png', $png]]))->status);
        $this->assertSame($key, $target->fresh()->avatar_key);
        $this->assertSame($bytes, file_get_contents($this->barrierDir.'/avatars/'.$key));
        $this->assertCount(1, glob($this->barrierDir.'/avatars/*'));
        $this->assertSame(200, $browser->request('POST', $path, options: $this->multipart($update, [['avatar', 'fixture.png', $exact]]))->status);
        $this->assertFileDoesNotExist($this->barrierDir.'/avatars/'.$key);
        $this->assertCount(1, glob($this->barrierDir.'/avatars/*'));
    }

    public function test_founder_avatar_and_custom_last_admin_invariant_on_actual_actions(): void
    {
        $founder = $this->user('Fixture.Founder');
        $role = Role::create(['name' => 'fixture-viable', 'guard_name' => 'web']);
        $role->syncPermissions(IdentityPermissions::ESSENTIAL);
        $backup = $this->user('Fixture.Backup', $role->name);
        $server = $this->server();
        $a = $this->browser($server);
        $b = $this->browser($server);
        $this->assertSame(200, $a->login($founder->username)->status);
        $this->assertSame(200, $b->login($backup->username)->status);
        $path = '/api/v1/users/'.$founder->id;
        $fields = $this->fields($founder, 'manager');
        $image = [['avatar', 'fixture.png', AvatarFixtures::image('png')]];
        $this->assertSame(403, $b->request('POST', $path, options: $this->multipart($fields + ['_method' => 'PUT'], $image))->status);
        $this->assertNull($founder->fresh()->avatar_key);
        $this->assertSame([], glob($this->barrierDir.'/avatars/*'));
        $this->assertSame(200, $a->request('POST', $path, options: $this->multipart($fields + ['_method' => 'PUT'], $image))->status);
        $oldKey = $founder->fresh()->avatar_key;
        $this->assertFileExists($this->barrierDir.'/avatars/'.$oldKey);
        $this->assertSame(403, $b->request('PUT', $path, $fields + ['removeAvatar' => true])->status);
        $this->assertSame(403, $a->request('PUT', $path, $fields + ['password' => 'synthetic-password', 'passwordConfirmation' => 'synthetic-password'])->status);
        $this->assertSame(403, $a->request('PUT', $path, array_replace($fields, ['isActive' => false]))->status);
        $this->assertSame($founder->password, $founder->fresh()->password);
        $this->assertSame($oldKey, $founder->fresh()->avatar_key);
        $id = $a->sessionId();
        $this->assertSame(200, $a->request('PUT', $path, $this->fields($founder, 'reception'))->status);
        $this->assertSame($id, $a->sessionId());
        $me = $a->me();
        $this->assertSame(200, $me->status);
        $this->assertSame('reception', $me->json()['data']['role']['name']);
        $this->assertNotContains('identity.users.update', $me->json()['data']['permissions']);
        $this->assertSame(403, $a->request('PUT', $path, $fields)->status);
        $this->assertSame(1, $founder->fresh()->auth_version);
        $backupPath = '/api/v1/users/'.$backup->id;
        $backupFields = array_replace($this->fields($backup, $role->name), ['isActive' => false]);
        $response = $b->request('POST', $backupPath, options: $this->multipart($backupFields + ['_method' => 'PUT'], $image));
        $this->assertSame(409, $response->status);
        $this->assertSame('IDENTITY_LAST_ADMIN', $response->json()['errors']['code']);
        $this->assertTrue($backup->fresh()->is_active);
        $this->assertSame(1, $backup->fresh()->auth_version);
        $this->assertNull($backup->fresh()->avatar_key);
        $this->assertCount(1, glob($this->barrierDir.'/avatars/*'));
        $this->assertSame(200, $b->me()->status);
        $this->assertSame(200, $a->request('PUT', '/api/v1/profile/password', [
            'currentPassword' => 'test-fixture-only-password', 'password' => 'new-synthetic-profile-password',
            'passwordConfirmation' => 'new-synthetic-profile-password',
        ])->status);
        $this->assertTrue(Hash::check('new-synthetic-profile-password', $founder->fresh()->password));
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $b->me()->status);
    }

    public function test_wrong_guard_and_inactive_role_assignment_fail_without_repair(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = User::factory()->create(['username' => 'Fixture.WrongGuard']);
        $wrongRole = Role::create(['name' => 'fixture-wrong', 'guard_name' => 'other']);
        DB::table('model_has_roles')->insert(['role_id' => $wrongRole->id, 'model_type' => $target->getMorphClass(), 'model_id' => $target->id]);
        $inactive = Role::create(['name' => 'fixture-inactive', 'guard_name' => 'web', 'is_active' => false]);
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($founder->username)->status);
        $path = '/api/v1/users/'.$target->id;
        $this->assertSame(422, $browser->request('POST', '/api/v1/users', array_replace($this->createFields('Fixture.New'), ['roleId' => $inactive->id]))->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/users', array_replace($this->createFields('Fixture.New'), ['roleId' => $wrongRole->id]))->status);
        $this->assertSame(409, $browser->request('PUT', $path, $this->fields($target, 'reception'))->status);
        $this->assertSame(409, $browser->request('DELETE', $path)->status);
        $this->assertSame($wrongRole->id, (int) DB::table('model_has_roles')->where('model_id', $target->id)->value('role_id'));
        $this->assertSame(2, User::count());
    }

    private function createFields(string $username): array
    {
        return ['name' => 'Fixture new', 'username' => $username, 'roleId' => Role::findByName('reception', 'web')->id,
            'password' => 'synthetic-password', 'passwordConfirmation' => 'synthetic-password'];
    }

    private function fields(User $target, string $role): array
    {
        return ['name' => $target->name, 'username' => $target->username, 'email' => $target->email, 'phone' => $target->phone,
            'roleId' => Role::findByName($role, 'web')->id, 'isActive' => true];
    }

    private function multipart(array $fields, array $files): array
    {
        $boundary = 'kids-users-actual-integration';
        $body = '';
        foreach ($fields as $name => $value) {
            $value = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }
        foreach ($files as [$name, $filename, $bytes]) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\nContent-Type: application/octet-stream\r\n\r\n{$bytes}\r\n";
        }

        return ['raw' => $body."--{$boundary}--\r\n", 'headers' => ['Content-Type' => 'multipart/form-data; boundary='.$boundary]];
    }
}
