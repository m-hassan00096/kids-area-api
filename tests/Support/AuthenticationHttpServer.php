<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

final class AuthenticationHttpServer
{
    public const TEST_DUMMY = '$argon2id$v=19$m=8192,t=2,p=1$VXZMTGdGRzRXUEZHdG5zRg$Tq/XeZvIeRa3oYWgvheX5uEv6srKIg++9oUCo2B4diY';

    public readonly string $url;

    public readonly string $origin;

    private Process $process;

    public function __construct(string $barrierDir, bool $proxy = false, bool $secure = false, bool $fullCost = false)
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'kids_area_test'
            || DB::connection()->getConfig('username') !== 'kids_area_ci'
            || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException('Unsafe authentication HTTP test environment.');
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('Unable to allocate test HTTP port.');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $this->url = 'http://'.$address;
        $this->origin = ($secure ? 'https://' : 'http://').$address;
        $connection = DB::connection();
        $this->process = new Process(
            [PHP_BINARY, '-S', $address, base_path('tests/Support/authentication-router.php')],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'APP_DEBUG' => 'true',
                'APP_KEY' => (string) config('app.key'),
                'APP_URL' => $this->origin,
                'APP_LOCALE' => 'ar',
                'APP_FALLBACK_LOCALE' => 'ar',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) $connection->getConfig('host'),
                'DB_PORT' => (string) $connection->getConfig('port'),
                'DB_DATABASE' => 'kids_area_test',
                'DB_USERNAME' => 'kids_area_ci',
                'DB_PASSWORD' => (string) $connection->getConfig('password'),
                'DB_URL' => '',
                'SESSION_DRIVER' => 'database',
                'SESSION_SECURE_COOKIE' => $secure ? 'true' : 'false',
                'SESSION_DOMAIN' => '',
                'CACHE_STORE' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'IDENTITY_API_ORIGIN' => $this->origin,
                'IDENTITY_FRONTEND_ORIGINS' => $this->origin,
                'IDENTITY_TRUSTED_PROXIES' => $proxy || $secure ? '127.0.0.1' : '',
                'IDENTITY_DUMMY_HASH' => $fullCost ? '' : self::TEST_DUMMY,
                'ARGON_MEMORY' => $fullCost ? '65536' : '8192',
                'ARGON_TIME' => $fullCost ? '4' : '2',
                'ARGON_THREADS' => '1',
                'LOG_CHANNEL' => 'errorlog',
                'LOG_LEVEL' => 'debug',
                'IDENTITY_TEST_BARRIER_DIR' => $barrierDir,
                'PHP_CLI_SERVER_WORKERS' => false,
            ],
            null,
            180,
        );
        $this->process->start();
        $deadline = microtime(true) + 5;
        do {
            $connection = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            if (! $this->process->isRunning()) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        $this->stop();
        throw new RuntimeException('Isolated authentication HTTP server did not start.');
    }

    public function diagnostic(): string
    {
        // Test server has synthetic data only. Emit only a safe error category,
        // method/class identifiers and source location, not raw exception text.
        $output = $this->process->getErrorOutput();
        preg_match_all('/(?:Uncaught\s+[A-Za-z0-9_\\\\]+|Call to undefined method [A-Za-z0-9_\\\\:]+|Target class \[[A-Za-z0-9_\\\\.-]+\] does not exist|Class "[A-Za-z0-9_\\\\]+" not found|Cannot redeclare [A-Za-z0-9_\\\\:]+|[A-Za-z0-9_.-]+\.php(?::| on line )\d+)/', $output, $matches);

        return implode('; ', array_slice(array_unique($matches[0]), -12));
    }

    public function stop(): void
    {
        if ($this->process->isRunning()) {
            $this->process->stop(2);
        }
    }
}
