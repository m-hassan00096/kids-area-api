<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\UsersController;
use Modules\Identity\Http\Middleware\FinalizeLoginSession;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;
use Modules\Identity\Services\LoginThrottle;
use Tests\Support\AuthenticationCommitFault;
use Tests\Support\UsersBoundaryProbe;

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
if (is_string($mode) && str_starts_with($mode, 'users-probe-') && $request->is('api/v1/users', 'api/v1/users/*')) {
    // Swap only the unimplemented controller in the guarded test router. The real route and middleware stack runs unchanged.
    $app->bind(UsersController::class, fn () => new UsersBoundaryProbe);
}

// Isolated test-only route: prove ordinary API query events remain visible
// while real native session reads, writes and garbage collection are private.
Route::middleware('api')->get('/api/v1/__auth-review-probe', static function () use ($mode) {
    if ($mode === 'http-503') {
        abort(503, 'synthetic-redaction-sentinel');
    }
    if ($mode === 'http-403') {
        abort(403, 'synthetic-redaction-sentinel');
    }
    DB::selectOne('SELECT 1 AS synthetic_probe');

    return response()->json(['success' => true]);
});
Route::middleware(['api', RequireStatefulBrowser::class, 'auth:sanctum', RequireStaffSession::class])
    ->get('/api/v1/__auth-review-protected-probe', static function () {
        DB::selectOne('SELECT 1 AS synthetic_probe');

        return response()->json(['success' => true]);
    });
if ($mode === 'probe-events') {
    config()->set('session.lottery', [1, 1]);
}
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
    $fields = ['password', 'currentPassword', 'passwordConfirmation'];
    if ($request->is('api/v1/users', 'api/v1/users/*')) {
        $fields = [...$fields, 'email', 'phone'];
        $secrets = [...$secrets, UsersBoundaryProbe::KEY, UsersBoundaryProbe::PATH, UsersBoundaryProbe::RAW];
    }
    foreach ($fields as $field) {
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
$usersTargetReads = 0;
$usersSensitiveReads = 0;
$timing = ['first_throttle_sql' => null, 'after_throttle_sql' => null, 'manager_start' => null];
$armed = true;
$restoreStatements = 0;
$connection->beforeExecuting(static function (string $query) use ($mode, $pause, &$armed, &$count, &$credentialReads, &$usersTargetReads, &$usersSensitiveReads, &$secrets, &$timing, &$restoreStatements, $request): void {
    $count++;
    $sql = strtolower(str_replace(chr(96), '', $query));
    if (str_starts_with($sql, 'select') && str_contains($sql, 'from users') && str_contains($sql, 'avatar_key')) {
        $usersTargetReads++;
    }
    if (str_contains($sql, 'synthetic_users_sensitive_probe')) {
        $usersSensitiveReads++;
    }
    $ns = hrtime(true);
    if (str_starts_with($sql, 'set session innodb_lock_wait_timeout =')) {
        $restoreStatements++;
        if ($restoreStatements === 2 && in_array($mode, ['fault-restore-after-commit', 'fault-restore-with-timeout'], true)) {
            throw new RuntimeException('Synthetic timeout restoration failure.');
        }
    }
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
        'fault-final-native-save' => $nativeSave && DB::transactionLevel() === 0 && $request->attributes->has(FinalizeLoginSession::CANDIDATE),
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

// Simulate a lost acknowledgement only after a real MySQL COMMIT, without
// changing production code, using a guarded disposable test connection.
$commitFault = null;
if ($mode === 'fault-commit-ack') {
    $commitFault = new AuthenticationCommitFault($connection->getPdo(), $connection);
    $connection->setPdo($commitFault);
}

$response = $kernel->handle($request);
if ($commitFault !== null) {
    $response->headers->set('X-Test-Actual-Commit-Count', (string) $commitFault->commits);
}
$response->headers->set('X-Test-Query-Count', (string) $count);
if ($diagnostic !== null) {
    $response->headers->set('X-Test-Exception-Class', $diagnostic);
}
$response->headers->set('X-Test-Credential-Reads', (string) $credentialReads);
$response->headers->set('X-Test-Users-Target-Reads', (string) $usersTargetReads);
$response->headers->set('X-Test-Users-Sensitive-Reads', (string) $usersSensitiveReads);
if ($request->attributes->has('_test_users_fingerprint')) {
    $response->headers->set('X-Test-Users-Fingerprint', $request->attributes->get('_test_users_fingerprint'));
}
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
    $eventVisible = collect($queries)->contains(fn ($query) => str_contains($query[0], 'synthetic_probe'));
    $logVisible = collect($queryLog)->contains(fn ($query) => str_contains($query['query'], 'synthetic_probe'));
    $nativeVisible = collect($queries)->contains(fn ($query) => str_contains(strtolower($query[0]), 'sessions'))
        || collect($queryLog)->contains(fn ($query) => str_contains(strtolower($query['query']), 'sessions'));
    $response->headers->set('X-Test-Business-Query-Visible', $eventVisible && $logVisible ? '1' : '0');
    $usersVisible = collect($queries)->contains(fn ($query) => str_contains($query[0], 'synthetic_users_sensitive_probe'))
        || collect($queryLog)->contains(fn ($query) => str_contains($query['query'], 'synthetic_users_sensitive_probe'));
    $response->headers->set('X-Test-Users-Sensitive-Query-Visible', $usersVisible ? '1' : '0');
    $response->headers->set('X-Test-Native-Session-Query-Visible', $nativeVisible ? '1' : '0');
    $correlated = collect($logs)->contains(fn ($log) => $log[0] === 'API operation failed.' && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID'));
    $response->headers->set('X-Test-Correlated-Report', $correlated ? '1' : '0');
    $response->headers->set('X-Test-Error-Report-Count', (string) collect($logs)->filter(fn ($log) => $log[0] === 'API operation failed.')->count());
    $response->headers->set('X-Test-Restore-Failure', collect($logs)->contains(fn ($log) => $log[0] === 'Login lock timeout restoration failed.') ? '1' : '0');
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
