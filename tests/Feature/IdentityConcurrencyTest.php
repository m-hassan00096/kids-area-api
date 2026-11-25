<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\IdentityAccess;
use Modules\Identity\Services\IdentityMutex;
use Modules\Identity\Support\IdentityPermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

class IdentityConcurrencyTest extends IdentityDatabaseTestCase
{
    public function test_concurrent_first_seeders_produce_one_catalogue_and_explicit_grants(): void
    {
        [$first, $second] = $this->race(['mode' => 'seed'], ['mode' => 'seed']);

        $this->assertSame('ok', $first['code']);
        $this->assertSame('ok', $second['code']);
        $this->assertSame(2, Role::count());
        $this->assertSame(10, Permission::count());
        $this->assertEqualsCanonicalizing(IdentityPermissions::keys(), Role::findByName('manager', 'web')->permissions()->pluck('name')->all());
        $this->assertSame(0, Role::findByName('reception', 'web')->permissions()->count());
        $this->assertSame(0, User::count());
    }

    #[DataProvider('founderNames')]
    public function test_overlapping_founder_commands_commit_only_one_id_one(string $secondName): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();

        [$first, $second] = $this->race(
            ['mode' => 'bootstrap', 'username' => 'Founder.A'],
            ['mode' => 'bootstrap', 'username' => $secondName],
        );

        $this->assertSame('ok', $first['code']);
        $this->assertSame('existing-user', $second['code']);
        $founder = User::sole();
        $this->assertSame(1, $founder->id);
        $this->assertTrue($founder->is_active);
        $this->assertSame('Founder.A', $founder->username);
        $this->assertSame(['manager'], $founder->roles()->pluck('name')->all());
        $this->assertSame(1, DB::table('model_has_roles')->count());
        $this->assertSame(0, DB::table('model_has_permissions')->count());
        $this->assertEqualsCanonicalizing(IdentityPermissions::keys(), $founder->getAllPermissions()->pluck('name')->all());
    }

    public static function founderNames(): array
    {
        return [['Founder.B'], ['Founder.A']];
    }

    #[DataProvider('uniqueRaces')]
    public function test_real_concurrent_duplicate_constraints(array $firstData, array $secondData, string $index): void
    {
        [$first, $second] = $this->race(
            ['mode' => 'unique', 'attributes' => $firstData + ['is_active' => false]],
            ['mode' => 'unique', 'attributes' => $secondData],
        );

        $this->assertSame('ok', $first['code']);
        $this->assertSame('duplicate', $second['code']);
        $this->assertSame($index, $second['index']);
        $this->assertSame(1, User::count());
    }

    public static function uniqueRaces(): array
    {
        return [
            [['username' => 'Exact.Name'], ['username' => 'Exact.Name'], 'users_username_unique'],
            [['email' => 'Ahmed@Gmail.com'], ['email' => 'ahmed@gmail.com'], 'users_email_unique'],
            [['phone' => '00012345678'], ['phone' => '00012345678'], 'users_phone_unique'],
        ];
    }

    public function test_concurrent_grant_change_and_deactivation_cannot_strand_administration(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $manager = Role::findByName('manager', 'web');
        $founder = User::factory()->create();
        $founder->syncRoles([$manager]);
        $manager->revokePermissionTo(Permission::where('name', 'identity.users.view')->sole());

        $roleA = Role::create(['name' => 'custom-a', 'alias_name' => 'Custom A', 'guard_name' => 'web']);
        $roleB = Role::create(['name' => 'custom-b', 'alias_name' => 'Custom B', 'guard_name' => 'web']);
        $permissions = Permission::whereIn('name', IdentityPermissions::ESSENTIAL)->get();
        $roleA->syncPermissions($permissions);
        $roleB->syncPermissions($permissions);
        $a = User::factory()->create();
        $a->syncRoles([$roleA]);
        $b = User::factory()->create();
        $b->syncRoles([$roleB]);

        // A separately authorized operator stays able to attempt both mutations but is not viable.
        $operatorRole = Role::create(['name' => 'operator', 'alias_name' => 'Operator', 'guard_name' => 'web']);
        $operatorRole->syncPermissions(Permission::whereIn('name', ['identity.roles.update', 'identity.users.update'])->get());
        $operator = User::factory()->create();
        $operator->syncRoles([$operatorRole]);

        [$first, $second] = $this->race(
            ['mode' => 'access', 'actor' => $operator->id, 'permission' => 'identity.roles.update', 'change' => 'revoke', 'target' => $roleA->id],
            ['mode' => 'access', 'actor' => $operator->id, 'permission' => 'identity.users.update', 'change' => 'deactivate', 'target' => $b->id],
        );

        $this->assertSame('ok', $first['code']);
        $this->assertSame('IDENTITY_LAST_ADMIN', $second['code']);
        $this->assertSame(409, $second['status']);
        $this->assertFalse(app(IdentityAccess::class)->allows($a->id, 'identity.users.view'));
        $this->assertTrue($b->fresh()->is_active);
        $this->assertTrue(DB::transaction(function () use ($b): bool {
            app(IdentityMutex::class)->lock();

            return app(IdentityAccess::class)->currentHasAll(IdentityPermissions::ESSENTIAL, $b->id);
        }));
    }

    /**
     * The first worker holds a real transaction at a test-only barrier. The second
     * starts a separate connection and is observed executing the contested SQL
     * on MySQL before the first worker is released. No parent transaction or sleep-only race.
     */
    private function race(array $first, array $second): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'identity-race-'.bin2hex(random_bytes(12));
        $a = $this->worker($first + ['hold' => true, 'barrier' => $barrier]);
        $b = $this->worker($second + ['hold' => false, 'barrier' => $barrier]);

        try {
            $a->start();
            $firstReady = $this->awaitEvent($a, 'ready');
            $this->awaitEvent($a, 'held');

            $b->start();
            $secondReady = $this->awaitEvent($b, 'ready');
            $this->awaitEvent($b, 'attempt');

            $this->assertNotSame($firstReady['connection_id'], $secondReady['connection_id']);
            $this->assertNotSame((int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $secondReady['connection_id']);
            $this->awaitContestedQuery($b, $secondReady['connection_id'], $second['mode']);
            $this->assertTrue($a->isRunning(), 'The first transaction must still be held.');

            file_put_contents($barrier, 'release');
            $firstExit = $a->wait();
            $secondExit = $b->wait();
            $firstResult = $this->event($a, 'result');
            $secondResult = $this->event($b, 'result');
            $this->assertNotNull($firstResult, 'First process did not return a safe result.');
            $this->assertNotNull($secondResult, 'Second process did not return a safe result.');
            $this->assertSame(0, $firstExit, json_encode($firstResult));
            $this->assertSame($secondResult['code'] === 'ok' ? 0 : ($second['mode'] === 'bootstrap' ? 1 : 10), $secondExit, json_encode($secondResult));

            return [$firstResult, $secondResult];
        } finally {
            // Release and terminate only these synthetic workers, even after failed assertions.
            file_put_contents($barrier, 'release');
            if ($a->isRunning()) {
                $a->stop(2);
            }
            if ($b->isRunning()) {
                $b->stop(2);
            }
            unlink($barrier);
        }
    }

    private function worker(array $data): Process
    {
        $connection = DB::connection();

        return new Process(
            [PHP_BINARY, base_path('tests/Support/identity-worker.php')],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) $connection->getConfig('host'),
                'DB_PORT' => (string) $connection->getConfig('port'),
                'DB_DATABASE' => 'kids_area_test',
                'DB_USERNAME' => 'kids_area_ci',
                'DB_PASSWORD' => (string) $connection->getConfig('password'),
                'DB_URL' => '',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
            ],
            json_encode($data, JSON_THROW_ON_ERROR),
            25,
        );
    }

    private function event(Process $process, string $event): ?array
    {
        foreach (explode(PHP_EOL, $process->getOutput()) as $line) {
            $data = json_decode($line, true);
            if (is_array($data) && ($data['event'] ?? null) === $event) {
                return $data;
            }
        }

        return null;
    }

    private function awaitEvent(Process $process, string $event): array
    {
        $deadline = microtime(true) + 8;
        do {
            $process->checkTimeout();
            if (($result = $this->event($process, $event)) !== null) {
                return $result;
            }
            if (! $process->isRunning()) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        $this->fail('Worker did not reach '.$event.': '.json_encode($this->event($process, 'result')));
    }

    private function awaitContestedQuery(Process $process, int $connectionId, string $mode): void
    {
        $deadline = microtime(true) + 5;
        do {
            if (! $process->isRunning()) {
                break;
            }

            // MySQL allows an unprivileged account to inspect its own sessions.
            foreach (DB::select('SHOW FULL PROCESSLIST') as $entry) {
                $row = array_change_key_case((array) $entry);
                if ((int) $row['id'] !== $connectionId || $row['command'] !== 'Query') {
                    continue;
                }

                $query = strtolower(str_replace(chr(96), '', (string) $row['info']));
                $contested = match ($mode) {
                    'seed' => str_starts_with($query, 'insert into roles'),
                    'unique' => str_starts_with($query, 'insert into users'),
                    'access', 'bootstrap' => str_contains($query, 'from roles') && str_contains($query, 'for update'),
                };
                if ($contested) {
                    $this->assertTrue($process->isRunning());

                    return;
                }
            }

            usleep(20000);
        } while (microtime(true) < $deadline);

        $this->fail('Second independent MySQL connection was not observed executing the contested query while the first transaction was held.');
    }
}
