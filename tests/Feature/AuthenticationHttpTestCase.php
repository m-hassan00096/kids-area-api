<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationHttpServer;

abstract class AuthenticationHttpTestCase extends IdentityDatabaseTestCase
{
    protected string $barrierDir;

    private array $servers = [];

    protected function setUp(): void
    {
        parent::setUp();
        app(SeedIdentityCatalogueAction::class)->execute();
        $this->barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kids-auth-'.bin2hex(random_bytes(10));
        $this->assertTrue(mkdir($this->barrierDir, 0700));
    }

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            $server->stop();
        }
        if (isset($this->barrierDir) && is_dir($this->barrierDir)) {
            foreach (glob($this->barrierDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->barrierDir);
        }
        parent::tearDown();
    }

    protected function server(bool $proxy = false, bool $secure = false, bool $fullCost = false): AuthenticationHttpServer
    {
        $server = new AuthenticationHttpServer($this->barrierDir, $proxy, $secure, $fullCost);
        $this->servers[] = $server;

        return $server;
    }

    protected function browser(AuthenticationHttpServer $server, bool $handshake = true): AuthenticationBrowser
    {
        $browser = new AuthenticationBrowser($server);
        if ($handshake) {
            $response = $browser->handshake();
            $this->assertSame(204, $response->status, 'Handshake: '.($response->header('X-Test-Exception-Class') ?? 'no application exception class').'; '.$server->diagnostic());
            $this->assertSame('', $response->body);
            $this->assertNotNull($response->header('X-Request-ID'));
        }

        return $browser;
    }

    protected function user(string $username, string $role = 'manager', array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['username' => $username]);
        $user->syncRoles([Role::findByName($role, 'web')]);

        return $user->fresh();
    }

    protected function binding(AuthenticationBrowser $browser): ?object
    {
        return DB::table('sessions')->where('id', $browser->sessionId())->first();
    }
}
