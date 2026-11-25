<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Identity\Services\LoginThrottle;

require __DIR__.'/../../vendor/autoload.php';

// This router exists only in tests and must never serve a real environment.
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing'
    || getenv('DB_DATABASE') !== 'kids_area_test' || getenv('DB_USERNAME') !== 'kids_area_ci'
    || ! in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost'], true)
    || getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_URL') !== '') {
    http_response_code(503);
    exit;
}

$request = Request::capture();
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance('request', $request);
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config()->set('identity-browser.throttle_cleanup_lottery', [0, 100]);
$connection = DB::connection();
if (! $app->environment('testing') || $connection->getDatabaseName() !== 'kids_area_test'
    || $connection->getDriverName() !== 'mysql' || $connection->getConfig('username') !== 'kids_area_ci'
    || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost'], true)) {
    http_response_code(503);
    exit;
}
$server = $connection->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@default_storage_engine AS engine, CONNECTION_ID() AS connection_id');
if ($server->db !== 'kids_area_test' || ! str_starts_with($server->version, '8.') || $server->engine !== 'InnoDB' || $app->runningInConsole()) {
    http_response_code(503);
    exit;
}

$time = $request->headers->get('X-Identity-Test-Time');
if (is_string($time) && ctype_digit($time)) {
    Carbon::setTestNow(Carbon::createFromTimestampMs((int) $time, 'UTC'));
}

$barrierDir = getenv('IDENTITY_TEST_BARRIER_DIR');
$barrier = $request->headers->get('X-Identity-Test-Barrier');
$mode = $request->headers->get('X-Identity-Test-Mode');
$prefix = null;
if ($barrier !== null) {
    if (! is_string($barrierDir) || dirname($barrierDir) !== sys_get_temp_dir()
        || ! str_starts_with(basename($barrierDir), 'kids-auth-') || ! is_dir($barrierDir)
        || preg_match('/\A[a-zA-Z0-9_-]{1,50}\z/', $barrier) !== 1) {
        http_response_code(503);
        exit;
    }
    $prefix = $barrierDir.DIRECTORY_SEPARATOR.$barrier;
    file_put_contents($prefix.'.ready', json_encode(['connection_id' => (int) $server->connection_id], JSON_THROW_ON_ERROR));
}

$pause = static function () use ($prefix): void {
    if ($prefix === null) {
        throw new RuntimeException('Synthetic barrier not configured.');
    }
    file_put_contents($prefix.'.held', 'held');
    $deadline = microtime(true) + 12;
    while (microtime(true) < $deadline) {
        clearstatcache(true, $prefix.'.release');
        if (is_file($prefix.'.release')) {
            return;
        }
        usleep(20000);
    }
    throw new RuntimeException('Synthetic barrier timed out.');
};

$diagnostic = null;
Log::listen(static function (MessageLogged $event) use (&$diagnostic): void {
    if (is_string($event->context['exception_class'] ?? null)) {
        $diagnostic = $event->context['exception_class'];
    }
});
$inspect = $request->headers->get('X-Identity-Test-Inspect') === '1';
$logs = [];
$queries = [];
$secrets = ['synthetic-redaction-sentinel'];
if ($inspect) {
    foreach (['password', 'currentPassword', 'passwordConfirmation'] as $field) {
        $value = $request->input($field);
        if (is_string($value) && strlen($value) >= 8) {
            $secrets[] = $value;
        }
    }
    foreach ($request->cookies->all() as $value) {
        if (is_string($value) && strlen($value) >= 8) {
            $secrets[] = $value;
        }
    }
    foreach (DB::table('users')->pluck('password') as $hash) {
        $secrets[] = $hash;
    }
    foreach (DB::table('sessions')->get(['id', 'payload']) as $session) {
        $secrets[] = $session->id;
        $secrets[] = $session->payload;
    }
    Log::listen(static function (MessageLogged $event) use (&$logs): void {
        $logs[] = [$event->message, $event->context];
    });
    DB::listen(static function (QueryExecuted $event) use (&$queries): void {
        $queries[] = [$event->sql, $event->bindings];
    });
    $connection->enableQueryLog();
}

$count = 0;
$credentialReads = 0;
$timing = ['first_throttle_sql' => null, 'after_throttle_sql' => null, 'manager_start' => null];
$armed = true;
$connection->beforeExecuting(static function (string $query) use ($mode, $pause, &$armed, &$count, &$credentialReads, &$secrets, &$timing): void {
    $count++;
    $sql = strtolower(str_replace(chr(96), '', $query));
    $ns = hrtime(true);
    if ($timing['first_throttle_sql'] !== null && $timing['after_throttle_sql'] === null) {
        $timing['after_throttle_sql'] = $ns;
    }
    if ($timing['first_throttle_sql'] === null && str_starts_with($sql, 'insert into identity_login_throttles')) {
        $timing['first_throttle_sql'] = $ns;
    }
    if ($timing['manager_start'] === null && str_contains($sql, 'from roles') && str_contains($sql, 'for update')) {
        $timing['manager_start'] = $ns;
    }
    $credentialRead = str_starts_with($sql, 'select') && str_contains($sql, 'from users') && str_contains($sql, 'username');
    if ($credentialRead) {
        $credentialReads++;
    }
    if (! $armed) {
        return;
    }
    $managerLock = str_contains($sql, 'from roles') && str_contains($sql, 'for update');
    $marker = str_starts_with($sql, 'update sessions set auth_user_id');
    $passwordWrite = str_starts_with($sql, 'update users set') && str_contains($sql, 'password');
    $revoke = str_starts_with($sql, 'delete from sessions') && str_contains($sql, 'auth_user_id');
    $activity = str_starts_with($sql, 'update sessions set auth_last_activity');
    $nativeSave = str_starts_with($sql, 'update sessions set') && str_contains($sql, 'payload');
    $nativeInsert = str_starts_with($sql, 'insert into sessions');

    $hit = match ($mode) {
        'hold-credentials', 'fault-report' => $credentialRead,
        'hold-manager' => $managerLock,
        'hold-marker', 'fault-marker' => $marker,
        'hold-password', 'fault-password' => $passwordWrite,
        'fault-revoke' => $revoke,
        'fault-logout' => str_starts_with($sql, 'delete from sessions') && str_contains($sql, 'where id ='),
        'hold-late-insert' => $activity,
        'hold-native-save' => $nativeSave,
        'fault-native-insert' => $nativeInsert,
        default => false,
    };
    if (! $hit) {
        return;
    }
    $armed = false;
    if (str_starts_with($mode, 'fault-')) {
        throw new RuntimeException('Synthetic failure: '.implode(' ', $secrets));
    }
    if ($mode === 'hold-late-insert') {
        // Exercise native late INSERT, not merely UPDATE of a deleted row.
        app('session')->driver()->setExists(false);
    }
    $pause();
});

if ($mode === 'cleanup') {
    $originalWait = (int) $connection->selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value;
    $connection->statement('SET SESSION innodb_lock_wait_timeout = 5');
    try {
        app(LoginThrottle::class)->cleanup();
    } finally {
        $connection->statement('SET SESSION innodb_lock_wait_timeout = '.$originalWait);
    }
}

$response = $kernel->handle($request);
$response->headers->set('X-Test-Query-Count', (string) $count);
if ($diagnostic !== null) {
    $response->headers->set('X-Test-Exception-Class', $diagnostic);
}
$response->headers->set('X-Test-Credential-Reads', (string) $credentialReads);
if ($timing['after_throttle_sql'] !== null) {
    $response->headers->set('X-Test-First-Throttle-Sql-Ms', (string) round(($timing['after_throttle_sql'] - $timing['first_throttle_sql']) / 1000000, 2));
}
if ($timing['manager_start'] !== null) {
    // Upper bound includes lock acquisition and native response completion.
    $response->headers->set('X-Test-Manager-Span-Ms', (string) round((hrtime(true) - $timing['manager_start']) / 1000000, 2));
}
if ($inspect) {
    $restored = $connection->logging() && $connection->getEventDispatcher() !== null;
    $queryLog = $connection->getQueryLog();
    $connection->disableQueryLog();
    foreach (DB::table('users')->pluck('password') as $hash) {
        $secrets[] = $hash;
    }
    foreach (DB::table('sessions')->get(['id', 'payload']) as $session) {
        $secrets[] = $session->id;
        $secrets[] = $session->payload;
    }
    foreach ($response->headers->getCookies() as $cookie) {
        $secrets[] = $cookie->getValue();
    }
    $log = json_encode($logs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $queryLog = json_encode([$queries, $queryLog], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $body = $response->getContent();
    $safe = true;
    foreach (array_unique($secrets) as $secret) {
        if (is_string($secret) && strlen($secret) >= 8
            && (str_contains($log, $secret) || str_contains($queryLog, $secret) || str_contains($body, $secret))) {
            $safe = false;
        }
    }
    $response->headers->set('X-Test-Redacted', $safe && $restored ? '1' : '0');
    $response->headers->set('X-Test-Safe-Report', str_contains($log, 'API operation failed.') ? '1' : '0');
}
$response->send();
$kernel->terminate($request, $response);
