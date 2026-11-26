<?php

namespace Tests\Feature;

use App\Support\SensitiveQueryScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use RuntimeException;

class UsersManagementBoundaryTest extends AuthenticationHttpTestCase
{
    public function test_independent_capability_before_target_lookup_and_fresh_manager_grants(): void
    {
        $target = $this->user('Fixture.Target');
        $server = $this->server();
        $path = '/api/v1/users/'.$target->id;
        $routes = [
            ['GET', '/api/v1/users', 'view', false],
            ['GET', $path, 'view', true],
            ['GET', $path.'/avatar', 'view', true],
            ['POST', '/api/v1/users', 'create', false],
            ['PUT', $path, 'update', true],
            ['DELETE', $path, 'delete', true],
        ];
        foreach (['view', 'create', 'update', 'delete'] as $capability) {
            $name = 'fixture-'.$capability;
            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'alias_name' => 'Fixture']);
            $role->givePermissionTo(Permission::findByName('identity.users.'.$capability, 'web'));
            $staff = $this->user('Fixture.'.$capability, $name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($staff->username)->status);
            foreach ($routes as [$method, $uri, $required, $lookup]) {
                $options = in_array($method, ['GET', 'HEAD'], true) ? [] : ['raw' => '{}'];
                $response = $browser->request($method, $uri, options: $options);
                $allowed = $capability === $required;
                $status = ! $allowed ? 403 : ($method === 'GET' ? (str_ends_with($uri, '/avatar') ? 404 : 200) : 503);
                $this->assertSame($status, $response->status, $capability.' '.$method.' '.$uri);
                $this->assertSame($allowed && $lookup ? '1' : '0', $response->header('X-Test-Users-Target-Reads'));
            }
            $missing = $browser->request('GET', '/api/v1/users/99999');
            $this->assertSame($capability === 'view' ? 404 : 403, $missing->status);
            $this->assertSame($capability === 'view' ? '1' : '0', $missing->header('X-Test-Users-Target-Reads'));
            $overflow = $browser->request('GET', '/api/v1/users/18446744073709551615');
            $this->assertSame($capability === 'view' ? 404 : 403, $overflow->status);
            $this->assertSame('0', $overflow->header('X-Test-Users-Target-Reads'));
            $this->assertSame(200, $browser->me()->status);
        }

        $manager = $this->browser($server);
        $this->assertSame(200, $manager->login($target->username)->status);
        $this->assertSame(200, $manager->request('GET', $path)->status);
        Role::findByName('manager', 'web')->revokePermissionTo('identity.users.view');
        $denied = $manager->request('GET', $path);
        $this->assertSame(403, $denied->status);
        $this->assertSame('0', $denied->header('X-Test-Users-Target-Reads'));
        $this->assertSame(200, $manager->me()->status);
    }

    public function test_exact_json_values_empty_presence_and_query_normalization(): void
    {
        $staff = $this->user('Fixture.Normalize');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($staff->username)->status);
        $headers = ['X-Identity-Test-Mode' => 'users-probe-normalized'];
        $path = '/api/v1/users/'.$staff->id;
        $input = ['name' => '  Fixture Name  ', 'username' => 'Case.User', 'email' => 'Case@Example.test',
            'phone' => '00012345678', 'roleId' => 2, 'isActive' => false, 'removeAvatar' => false,
            'password' => '  secret fixture  ', 'passwordConfirmation' => '  secret fixture  '];
        $expected = $input;
        $expected['name'] = 'Fixture Name';
        $response = $browser->request('PUT', $path, $input, ['headers' => $headers]);
        $this->assertSame(503, $response->status);
        $this->assertSame($this->digest($expected, [], $staff->id), $response->header('X-Test-Users-Fingerprint'));

        $input = ['email' => '', 'phone' => null, 'password' => '', 'passwordConfirmation' => null];
        $response = $browser->request('PUT', $path, $input, ['headers' => $headers]);
        $this->assertSame(503, $response->status);
        $this->assertSame($this->digest(['email' => null, 'phone' => null, 'password' => null, 'passwordConfirmation' => null], [], $staff->id), $response->header('X-Test-Users-Fingerprint'));
        $response = $browser->request('PUT', $path, ['name' => 'Fixture', 'isActive' => false], ['headers' => $headers]);
        $this->assertSame($this->digest(['name' => 'Fixture', 'isActive' => false], [], $staff->id), $response->header('X-Test-Users-Fingerprint'));
        $response = $browser->request('POST', '/api/v1/users', ['password' => '        ', 'passwordConfirmation' => '        '], ['headers' => $headers]);
        $this->assertSame(503, $response->status);
        $this->assertSame($this->digest(['password' => '        ', 'passwordConfirmation' => '        ']), $response->header('X-Test-Users-Fingerprint'));

        $query = ['filter' => ['search' => '  00,false  ', 'isActive' => '0', 'roleId' => '2']];
        $response = $browser->request('GET', '/api/v1/users?'.http_build_query($query), options: ['headers' => $headers]);
        $query['filter']['search'] = '00,false';
        $this->assertSame(503, $response->status);
        $this->assertSame($this->digest([], $query), $response->header('X-Test-Users-Fingerprint'));
    }

    public function test_rejects_wrong_types_exact_contact_whitespace_and_unsupported_fields(): void
    {
        $staff = $this->user('Fixture.Invalid');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($staff->username)->status);
        $path = '/api/v1/users/'.$staff->id;
        foreach ([
            ['username', ' Case.User'], ['username', ['Case.User']], ['name', 123], ['name', '   '],
            ['email', ' Case@Example.test'], ['email', '   '], ['phone', '00012345678 '], ['phone', 12345678901],
            ['roleId', '2'], ['roleId', true], ['roleId', 2.5], ['roleId', 0],
            ['isActive', 'false'], ['isActive', 0], ['removeAvatar', '1'], ['password', ['secret']],
            ['passwordConfirmation', 123], ['avatar', 'data:image/png;base64,synthetic'],
        ] as [$field, $value]) {
            $response = $browser->request('PUT', $path, [$field => $value]);
            $this->assertSame(422, $response->status, $field);
            $this->assertArrayHasKey($field, $response->json()['errors']);
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
        }
        foreach ([['avatar_key' => 'synthetic'], ['currentPassword' => 'synthetic'], ['role' => ['manager']], ['userId' => 1]] as $data) {
            $response = $browser->request('PUT', $path, $data);
            $this->assertSame(422, $response->status);
            $this->assertArrayHasKey('request', $response->json()['errors']);
        }
        $this->assertSame(422, $browser->request('POST', '/api/v1/users', ['isActive' => false])->status);
        $this->assertSame(422, $browser->request('PUT', $path.'?phone=00012345678', ['name' => 'Fixture'])->status);
        $this->assertSame(422, $browser->request('PUT', $path, options: ['raw' => '[]'])->status);
        $this->assertSame(400, $browser->request('PUT', $path, options: ['raw' => '{invalid'])->status);
        $this->assertSame(422, $browser->request('DELETE', $path, ['name' => 'Fixture'])->status);
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_native_multipart_preserves_exact_strings_and_normalizes_only_canonical_scalars(): void
    {
        $staff = $this->user('Fixture.Multipart');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($staff->username)->status);
        $path = '/api/v1/users/'.$staff->id;
        $data = ['_method' => 'PUT', 'name' => '  Fixture  ', 'username' => 'Exact.User', 'email' => '',
            'phone' => '00012345678', 'roleId' => '002', 'isActive' => '0', 'removeAvatar' => 'false',
            'password' => '  synthetic password  ', 'passwordConfirmation' => '  synthetic password  '];
        $expected = $data;
        unset($expected['_method']);
        $expected['name'] = 'Fixture';
        $expected['email'] = null;
        $expected['roleId'] = 2;
        $expected['isActive'] = false;
        $expected['removeAvatar'] = false;
        $response = $browser->request('POST', $path, options: $this->multipart($data, 'users-probe-normalized'));
        $this->assertSame(503, $response->status);
        $this->assertSame($this->digest($expected, [], $staff->id), $response->header('X-Test-Users-Fingerprint'));
        foreach (['yes', 'False', ' 0', '2', ''] as $value) {
            $response = $browser->request('POST', $path, options: $this->multipart(['_method' => 'PUT', 'isActive' => $value]));
            $this->assertSame(422, $response->status);
            $this->assertArrayHasKey('isActive', $response->json()['errors']);
        }
        foreach (['0', '-2', '2.0', ' 2', '18446744073709551616'] as $value) {
            $response = $browser->request('POST', $path, options: $this->multipart(['_method' => 'PUT', 'roleId' => $value]));
            $this->assertSame(422, $response->status);
            $this->assertArrayHasKey('roleId', $response->json()['errors']);
        }
    }

    public function test_safe_package_error_mapping_and_laravel_body_limit(): void
    {
        $staff = $this->user('Fixture.Errors');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($staff->username)->status);
        foreach (['filter', 'sort', 'include', 'fields'] as $field) {
            $response = $browser->request('GET', '/api/v1/users', options: ['headers' => [
                'X-Identity-Test-Mode' => 'users-probe-'.$field, 'X-Identity-Test-Inspect' => '1',
            ]]);
            $this->assertSame(422, $response->status, $field);
            $this->assertSame(['success' => false, 'message' => __('api.errors.validation'), 'errors' => [$field => [__('api.errors.validation')]]], $response->json());
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame('0', $response->header('X-Test-Error-Report-Count'));
        }
        foreach (['unknown' => [500, 'internal', '1'], 'conflict' => [409, 'conflict', '0']] as $mode => [$status, $key, $reports]) {
            $response = $browser->request('GET', '/api/v1/users', options: ['headers' => [
                'X-Identity-Test-Mode' => 'users-probe-'.$mode, 'X-Identity-Test-Inspect' => '1',
            ]]);
            $this->assertSame($status, $response->status);
            $this->assertSame(__('api.errors.'.$key), $response->json()['message']);
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame($reports, $response->header('X-Test-Error-Report-Count'));
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
        }
        $response = $browser->request('POST', '/api/v1/users', options: ['raw' => '{"name":"'.str_repeat('a', 4194304).'"}']);
        $this->assertSame(413, $response->status);
        $this->assertSame(['success' => false, 'message' => __('api.errors.too_large'), 'errors' => []], $response->json());
        $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
    }

    public function test_users_sensitive_queries_and_faults_are_redacted_without_hiding_unrelated_events(): void
    {
        $staff = $this->user('Fixture.Sensitive');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($staff->username)->status);
        $path = '/api/v1/users/'.$staff->id;
        $body = ['password' => 'synthetic-private-password', 'email' => 'private@example.test', 'phone' => '00098765432'];
        foreach ([
            ['POST', '/api/v1/users', $body, [], 'users-probe-fault', 500],
            ['PUT', $path, $body, [], 'users-probe-sensitive', 503],
            ['POST', $path, null, $this->multipart(['_method' => 'PUT'] + $body, 'users-probe-fault'), 'users-probe-fault', 500],
            ['GET', $path, null, [], 'users-probe-sensitive', 503],
        ] as [$method, $uri, $data, $options, $mode, $status]) {
            $options['headers'] = array_merge($options['headers'] ?? [], ['X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Inspect' => '1']);
            $response = $browser->request($method, $uri, $data, $options);
            $this->assertSame($status, $response->status);
            $this->assertSame('1', $response->header('X-Test-Users-Sensitive-Reads'));
            $this->assertSame('0', $response->header('X-Test-Users-Sensitive-Query-Visible'));
            $this->assertSame('0', $response->header('X-Test-Native-Session-Query-Visible'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame('1', $response->header('X-Test-Correlated-Report'));
            $this->assertSame('1', $response->header('X-Test-Error-Report-Count'));
        }
        $response = $browser->request('GET', '/api/v1/__auth-review-protected-probe', options: ['headers' => [
            'X-Identity-Test-Mode' => 'probe-events', 'X-Identity-Test-Inspect' => '1',
        ]]);
        $this->assertSame(200, $response->status);
        $this->assertSame('1', $response->header('X-Test-Business-Query-Visible'));
        $this->assertSame('0', $response->header('X-Test-Native-Session-Query-Visible'));
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_nested_sensitive_scope_restores_original_disabled_log_and_dispatcher_on_exception(): void
    {
        $connection = DB::connection();
        $dispatcher = $connection->getEventDispatcher();
        $logging = $connection->logging();
        $connection->disableQueryLog();
        try {
            SensitiveQueryScope::run($connection, function () use ($connection): void {
                $this->assertFalse($connection->logging());
                $this->assertNull($connection->getEventDispatcher());
                SensitiveQueryScope::run($connection, function () use ($connection): void {
                    $this->assertNull($connection->getEventDispatcher());
                    $this->assertFalse($connection->logging());
                    DB::selectOne('SELECT ? AS synthetic_scope_probe', ['synthetic-private-binding']);
                });
                $this->assertNull($connection->getEventDispatcher());
                throw new RuntimeException('Synthetic scope exception');
            });
            $this->fail('Expected synthetic scope fault.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic scope exception', $exception->getMessage());
            $this->assertSame($dispatcher, $connection->getEventDispatcher());
            $this->assertFalse($connection->logging());
        } finally {
            if ($logging) {
                $connection->enableQueryLog();
            }
        }
    }

    private function digest(array $body, array $query = [], ?int $target = null): string
    {
        return hash('sha256', serialize([$body, $query, $target]));
    }

    private function multipart(array $data, string $mode = ''): array
    {
        $boundary = 'kids-users-boundary-fixture';
        $raw = '';
        foreach ($data as $key => $value) {
            $raw .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$key}\"\r\n\r\n{$value}\r\n";
        }

        return ['raw' => $raw."--{$boundary}--\r\n", 'headers' => [
            'Content-Type' => 'multipart/form-data; boundary='.$boundary, 'X-Identity-Test-Mode' => $mode,
        ]];
    }
}
