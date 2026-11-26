<?php

use App\Exceptions\BusinessException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Actions\ProvisionActivityCatalogueAction;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\IdentityAccessMutation;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\IdentityPrompts;

require __DIR__.'/../../vendor/autoload.php';

function identityWorkerEvent(string $type, array $data = []): void
{
    fwrite(STDOUT, json_encode(['event' => $type] + $data, JSON_THROW_ON_ERROR).PHP_EOL);
    fflush(STDOUT);
}

// Only test processes may invoke this fixture. Never reset or connect to a real database.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing'
    || getenv('DB_DATABASE') !== 'kids_area_test' || getenv('DB_USERNAME') !== 'kids_area_ci'
    || ! in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost'], true)
    || getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_URL') !== '') {
    identityWorkerEvent('result', ['code' => 'unsafe-environment']);
    exit(90);
}

try {
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $connection = DB::connection();

    if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
        || $connection->getDatabaseName() !== 'kids_area_test'
        || $connection->getConfig('username') !== 'kids_area_ci'
        || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost'], true)) {
        throw new RuntimeException('Unsafe worker connection');
    }

    $server = $connection->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@default_storage_engine AS engine, CONNECTION_ID() AS connection_id');
    if ($server->db !== 'kids_area_test' || ! str_starts_with($server->version, '8.') || $server->engine !== 'InnoDB') {
        throw new RuntimeException('Unsafe worker database');
    }

    $connection->statement('SET SESSION innodb_lock_wait_timeout = 15');
    config()->set('hashing.argon.memory', 8192);
    config()->set('hashing.argon.time', 2);

    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $mode = $input['mode'];
    if ($mode === 'provision' && (getenv('GITHUB_ACTIONS') !== 'true' || $connection->getConfig('host') !== '127.0.0.1')) {
        throw new RuntimeException('Unsafe provisioning worker.');
    }
    $barrier = $input['barrier'];
    if (dirname($barrier) !== sys_get_temp_dir() || ! str_starts_with(basename($barrier), 'identity-race-')) {
        throw new RuntimeException('Invalid test barrier');
    }

    identityWorkerEvent('ready', ['connection_id' => (int) $server->connection_id]);

    $pause = function () use ($input, $barrier): void {
        if (! $input['hold']) {
            return;
        }

        identityWorkerEvent('held');
        $deadline = microtime(true) + 12;
        while (microtime(true) < $deadline) {
            clearstatcache(true, $barrier);
            if (is_file($barrier)) {
                return;
            }

            usleep(20000);
        }

        throw new RuntimeException('Test barrier timeout');
    };

    // Warm each independent process's package cache before the serialized write.
    if (in_array($mode, ['seed', 'bootstrap', 'access', 'provision'], true)) {
        app(PermissionRegistrar::class)->getPermissions();
    }

    $attempted = false;
    $held = false;
    $connection->beforeExecuting(function (string $query) use ($mode, $input, $pause, &$attempted, &$held): void {
        $sql = strtolower(str_replace(chr(96), '', $query));
        $managerLock = str_contains($sql, 'from roles') && str_contains($sql, 'for update');
        $attempt = match ($mode) {
            'seed' => str_starts_with($sql, 'insert into roles'),
            'bootstrap', 'access', 'provision' => $managerLock,
            'unique' => str_starts_with($sql, 'insert into users'),
        };

        if ($attempt && ! $attempted) {
            $attempted = true;
            identityWorkerEvent('attempt');
        }

        $holdPoint = match ($mode) {
            'seed' => str_starts_with($sql, 'insert into permissions'),
            'bootstrap' => str_starts_with($sql, 'insert into users'),
            'provision' => str_starts_with($sql, 'insert into identity_feature_provisions'),
            default => false,
        };

        if ($input['hold'] && $holdPoint && ! $held) {
            $held = true;
            $pause();
        }
    });

    switch ($mode) {
        case 'seed':
            app(SeedIdentityCatalogueAction::class)->execute();
            break;
        case 'provision':
            $applied = app(ProvisionActivityCatalogueAction::class)->execute();
            identityWorkerEvent('result', ['code' => 'ok', 'applied' => $applied]);
            exit(0);
        case 'bootstrap':
            $command = new IdentityPrompts;
            $command->setLaravel(app());
            $password = 'synthetic-race-password';
            $command->answers = ['Founder', $input['username'], '', '', $password, $password];
            $tester = new CommandTester($command);
            $exit = $tester->execute([], ['interactive' => true]);
            $output = $tester->getDisplay();
            if (str_contains($output, $password) || str_contains($output, '$argon2id$')) {
                throw new RuntimeException('Secret output');
            }

            identityWorkerEvent('result', [
                'code' => $exit === 0 ? 'ok' : (str_contains($output, __('identity.users_exist')) ? 'existing-user' : 'unexpected-refusal'),
            ]);
            exit($exit);
        case 'unique':
            DB::transaction(function () use ($input, $pause): void {
                User::factory()->create($input['attributes']);
                $pause();
            });
            break;
        case 'access':
            app(IdentityAccessMutation::class)->execute(
                $input['actor'],
                $input['permission'],
                function () use ($input, $pause): void {
                    $pause();
                    if (in_array($input['change'], ['revoke', 'revoke-activity'], true)) {
                        $role = Role::findOrFail($input['target']);
                        $permission = Permission::where('name', $input['change'] === 'revoke-activity' ? 'activities.store' : 'identity.users.view')->sole();
                        $role->revokePermissionTo($permission);
                    } else {
                        User::findOrFail($input['target'])->update(['is_active' => false]);
                    }
                },
            );
            break;
    }

    identityWorkerEvent('result', ['code' => 'ok']);
    exit(0);
} catch (BusinessException $exception) {
    identityWorkerEvent('result', ['code' => $exception->errorCode, 'status' => $exception->httpStatus]);
    exit(10);
} catch (QueryException $exception) {
    $index = null;
    foreach (['users_username_unique', 'users_email_unique', 'users_phone_unique'] as $name) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062 && str_contains((string) ($exception->errorInfo[2] ?? ''), $name)) {
            $index = $name;
        }
    }

    identityWorkerEvent('result', ['code' => $index === null ? 'unexpected-query' : 'duplicate', 'index' => $index]);
    exit(10);
} catch (Throwable $exception) {
    identityWorkerEvent('result', ['code' => 'unexpected', 'class' => $exception::class]);
    exit(91);
}
