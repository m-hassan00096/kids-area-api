<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Tests\Support\AvatarFixtures;

class UsersMutationValidationTest extends AuthenticationHttpTestCase
{
    public function test_actual_create_and_full_update_validate_without_mutating(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Exact.Target', 'reception', ['email' => 'Case@Example.test', 'phone' => '00012345678']);
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($founder->username)->status);
        $role = Role::findByName('reception', 'web');
        $create = ['name' => '  Fixture new  ', 'username' => 'Exact.New', 'roleId' => $role->id,
            'password' => '        ', 'passwordConfirmation' => '        '];
        $this->assertSame(503, $browser->request('POST', '/api/v1/users', $create)->status);
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
        $this->assertSame(503, $browser->request('POST', '/api/v1/users', array_replace($create, ['username' => 'exact.target']))->status);
        $path = '/api/v1/users/'.$target->id;
        $update = ['name' => '  Fixture edit  ', 'username' => $target->username, 'email' => $target->email, 'phone' => $target->phone,
            'roleId' => $role->id, 'isActive' => true];
        $this->assertSame(503, $browser->request('PUT', $path, $update)->status);
        foreach (array_keys($update) as $field) {
            $bad = $update;
            unset($bad[$field]);
            $response = $browser->request('PUT', $path, $bad);
            $this->assertSame(422, $response->status, $field);
            $this->assertArrayHasKey($field, $response->json()['errors']);
        }
        foreach ([
            ['email' => '', 'phone' => null, 'password' => '', 'passwordConfirmation' => null],
            ['password' => '  abcdef  ', 'passwordConfirmation' => '  abcdef  '],
            ['removeAvatar' => true],
        ] as $extra) {
            $this->assertSame(503, $browser->request('PUT', $path, array_replace($update, $extra))->status);
        }
        $response = $browser->request('PUT', $path, $update + ['passwordConfirmation' => 'orphan-fixture']);
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('passwordConfirmation', $response->json()['errors']);
        $this->assertSame(422, $browser->request('PUT', $path, $update + ['userId' => $founder->id])->status);
        $this->assertSame(422, $browser->request('PUT', $path, array_replace($update, ['username' => $founder->username]))->status);
        $this->assertSame(2, DB::table('users')->count());
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertSame($target->password, $target->fresh()->password);
    }

    public function test_early_founder_self_password_and_inactive_role_rules(): void
    {
        $founder = $this->user('Fixture.Founder');
        $actor = $this->user('Fixture.Actor');
        $inactive = Role::create(['name' => 'fixture-inactive', 'guard_name' => 'web', 'alias_name' => 'Fixture', 'is_active' => false]);
        $target = $this->user('Fixture.Target', $inactive->name);
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $data = ['name' => 'Fixture', 'username' => $target->username, 'email' => null, 'phone' => null, 'roleId' => $inactive->id, 'isActive' => true];
        $this->assertSame(403, $browser->request('PUT', '/api/v1/users/'.$founder->id, $data)->status);
        $self = array_replace($data, ['username' => $actor->username, 'roleId' => Role::findByName('manager', 'web')->id]);
        $this->assertSame(403, $browser->request('PUT', '/api/v1/users/'.$actor->id, $self + ['password' => '12345678', 'passwordConfirmation' => '12345678'])->status);
        $path = '/api/v1/users/'.$target->id;
        $this->assertSame(503, $browser->request('PUT', $path, $data)->status);
        $this->assertSame(503, $browser->request('PUT', $path, array_replace($data, ['isActive' => false]))->status);
        $target->update(['is_active' => false]);
        $response = $browser->request('PUT', $path, $data);
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('roleId', $response->json()['errors']);
        $otherRole = Role::create(['name' => 'fixture-other-inactive', 'guard_name' => 'web', 'alias_name' => 'Other', 'is_active' => false]);
        $this->assertSame(422, $browser->request('PUT', $path, array_replace($data, ['roleId' => $otherRole->id, 'isActive' => false]))->status);
        $founderBrowser = $this->browser($this->server());
        $this->assertSame(200, $founderBrowser->login($founder->username)->status);
        $founderData = array_replace($self, ['username' => $founder->username, 'isActive' => false]);
        $this->assertSame(403, $founderBrowser->request('PUT', '/api/v1/users/'.$founder->id, $founderData)->status);
    }

    public function test_actual_form_requests_normalize_native_avatar_without_staging(): void
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
        foreach ([['/api/v1/users', $create], ['/api/v1/users/'.$target->id, $update]] as [$path, $fields]) {
            $response = $browser->request('POST', $path, options: $this->multipart($fields, AvatarFixtures::image('png')));
            $this->assertSame(503, $response->status, $response->header('X-Test-Exception-Class') ?? '');
            $response = $browser->request('POST', $path, options: $this->multipart($fields, 'synthetic invalid image'));
            $this->assertSame(422, $response->status);
            $this->assertArrayHasKey('avatar', $response->json()['errors']);
        }
        $this->assertNull($target->fresh()->avatar_key);
        $this->assertSame(2, DB::table('users')->count());
        $this->assertSame([], glob($this->barrierDir.'/avatars/*'));
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
