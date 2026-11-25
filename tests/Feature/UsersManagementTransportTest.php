<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;

class UsersManagementTransportTest extends AuthenticationHttpTestCase
{
    public function test_only_approved_routes_have_the_existing_staff_transport(): void
    {
        $expected = [
            'api/v1/users' => ['GET', 'HEAD', 'POST'],
            'api/v1/users/{user}' => ['GET', 'HEAD', 'PUT', 'DELETE'],
            'api/v1/users/{user}/avatar' => ['GET', 'HEAD'],
            'api/v1/selects' => ['GET', 'HEAD'],
        ];
        $found = [];
        foreach (Route::getRoutes() as $route) {
            if (! isset($expected[$route->uri()])) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $this->assertContains('api', $middleware);
            $this->assertContains(RequireStatefulBrowser::class, $middleware);
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains(RequireStaffSession::class, $middleware);
            $found[$route->uri()] = array_merge($found[$route->uri()] ?? [], $route->methods());
            if (str_contains($route->uri(), '{user}')) {
                $this->assertSame('[1-9][0-9]*', $route->wheres['user']);
            }
        }
        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($found));
        foreach ($expected as $uri => $methods) {
            $this->assertEqualsCanonicalizing($methods, $found[$uri]);
        }
    }

    public function test_first_party_gate_and_guest_failures_cover_users_avatar_and_selects(): void
    {
        $server = $this->server();
        $browser = $this->browser($server);
        foreach (['/api/v1/users', '/api/v1/users/1', '/api/v1/users/1/avatar', '/api/v1/selects?selects=roles'] as $path) {
            $response = $browser->request('GET', $path);
            $this->assertSame(401, $response->status, $path);
            $this->assertSame(['success' => false, 'message' => __('api.errors.unauthenticated'), 'errors' => []], $response->json());
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
            foreach ([
                ['Origin' => 'https://unapproved.invalid'],
                ['Origin' => null, 'Referer' => null],
                ['Referer' => 'https://unapproved.invalid/'],
                ['Authorization' => 'Bearer synthetic-token'],
                ['Host' => 'unapproved.invalid'],
            ] as $headers) {
                $this->assertSame(403, $browser->request('GET', $path, options: ['headers' => $headers])->status, $path);
            }
        }

        $staff = $this->user('Fixture.Disabled');
        $this->assertSame(200, $browser->login($staff->username)->status);
        $staff->update(['is_active' => false]);
        $this->assertSame(403, $browser->request('GET', '/api/v1/users')->status);
        $this->assertSame(401, $browser->request('GET', '/api/v1/users')->status);
    }

    public function test_credentialed_delete_preflight_does_not_create_a_session(): void
    {
        $server = $this->server(splitOrigins: true);
        $browser = $this->browser($server, false);
        $before = DB::table('sessions')->count();
        $response = $browser->request('OPTIONS', '/api/v1/users/1', options: ['headers' => [
            'Origin' => 'https://staff.kids.example.test:443',
            'Access-Control-Request-Method' => 'DELETE',
            'Access-Control-Request-Headers' => 'X-XSRF-TOKEN,Content-Type',
        ]]);
        $this->assertSame(204, $response->status);
        $this->assertSame($server->origin, $response->header('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->header('Access-Control-Allow-Credentials'));
        $this->assertContains('DELETE', array_map('trim', explode(',', (string) $response->header('Access-Control-Allow-Methods'))));
        $this->assertSame($before, DB::table('sessions')->count());
        $this->assertSame(403, $browser->request('OPTIONS', '/api/v1/users/1', options: ['headers' => [
            'Origin' => 'https://unapproved.invalid',
            'Access-Control-Request-Method' => 'DELETE',
        ]])->status);
    }

    public function test_native_csrf_and_only_approved_multipart_method_override(): void
    {
        $staff = $this->user('Fixture.Transport');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($staff->username)->status);
        $before = DB::table('users')->count();

        foreach ([['POST', '/api/v1/users'], ['PUT', '/api/v1/users/1'], ['DELETE', '/api/v1/users/1']] as [$method, $path]) {
            foreach (['missing', 'bad'] as $csrf) {
                $this->assertSame(419, $browser->request($method, $path, [], ['csrf' => $csrf])->status);
            }
            // Authorized transport reaches the fail-closed placeholder; no CRUD exists yet.
            $this->assertSame(503, $browser->request($method, $path, [])->status);
        }

        $boundary = 'kids-users-transport-test';
        $parts = "--{$boundary}\r\nContent-Disposition: form-data; name=\"_method\"\r\n\r\nPUT\r\n"
            ."--{$boundary}\r\nContent-Disposition: form-data; name=\"avatar\"; filename=\"synthetic.png\"\r\n"
            ."Content-Type: image/png\r\n\r\n";
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        unset($image);
        $multipart = [
            'raw' => $parts.$png."\r\n--{$boundary}--\r\n",
            'headers' => ['Content-Type' => 'multipart/form-data; boundary='.$boundary],
        ];
        foreach (['missing', 'bad'] as $csrf) {
            $this->assertSame(419, $browser->request('POST', '/api/v1/users/1', options: $multipart + ['csrf' => $csrf])->status);
        }
        // No separate POST update route exists. A 503 proves native dispatch to PUT.
        $this->assertSame(503, $browser->request('POST', '/api/v1/users/1', options: $multipart)->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/users', options: $multipart)->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/users/1', options: array_replace($multipart, [
            'raw' => str_replace("\r\nPUT\r\n", "\r\nDELETE\r\n", $multipart['raw']),
        ]))->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/users/1?_method=PUT', [])->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/users/1', ['_method' => 'PUT'])->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/users/1', [], ['headers' => ['X-HTTP-Method-Override' => 'PUT']])->status);
        $this->assertSame(422, $browser->request('PUT', '/api/v1/users/1', ['_method' => 'PUT'])->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/auth/logout', options: $multipart)->status);
        $this->assertSame(405, $browser->request('POST', '/api/v1/users/1', [])->status);
        foreach (['0', '-1', 'abc'] as $id) {
            $this->assertSame(404, $browser->request('GET', '/api/v1/users/'.$id)->status);
        }
        $this->assertSame($before, DB::table('users')->count());
        $this->assertSame(200, $browser->me()->status);
    }
}
