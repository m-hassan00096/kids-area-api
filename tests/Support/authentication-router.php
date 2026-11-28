<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Hashing\HashManager;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Activities\Models\Activity;
use Modules\Identity\Actions\DeleteUserAction;
use Modules\Identity\Http\Controllers\UsersController;
use Modules\Identity\Http\Middleware\FinalizeLoginSession;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;
use Modules\Identity\Services\Avatar\AvatarStorage;
use Modules\Identity\Services\Avatar\NormalizedAvatar;
use Modules\Identity\Services\LoginThrottle;
use Modules\People\Models\Customer;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AuthenticationCommitFault;
use Tests\Support\RolesCacheBarrier;
use Tests\Support\UsersBoundaryProbe;
use Tests\Support\UsersMeasuredCommit;
use Tests\Support\UsersObservedStatement;

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
// Random GC may take unrelated session locks before a targeted SQL barrier.
// Make the synthetic harness deterministic; probe-events explicitly forces GC.
config()->set('session.lottery', [0, 100]);
$connection = DB::connection();
if (! $app->environment('testing') || $connection->getDatabaseName() !== 'kids_area_test'
    || $connection->getDriverName() !== 'mysql' || $connection->getConfig('username') !== 'kids_area_ci'
    || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost'], true)) {
    http_response_code(503);
    exit;
}
$server = $connection->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS principal, VERSION() AS version, @@default_storage_engine AS engine, CONNECTION_ID() AS connection_id');
if ($server->db !== 'kids_area_test' || ! str_starts_with($server->principal, 'kids_area_ci@') || ! str_starts_with($server->version, '8.') || $server->engine !== 'InnoDB' || $app->runningInConsole()) {
    http_response_code(503);
    exit;
}

$time = $request->headers->get('X-Identity-Test-Time');
if (is_string($time) && ctype_digit($time)) {
    Carbon::setTestNow(Carbon::createFromTimestampMs((int) $time, 'UTC'));
}

$barrierDir = getenv('IDENTITY_TEST_BARRIER_DIR');
if (is_string($barrierDir) && dirname($barrierDir) === sys_get_temp_dir()
    && str_starts_with(basename($barrierDir), 'kids-auth-') && is_dir($barrierDir) && ! is_link($barrierDir)) {
    config()->set('filesystems.disks.identity-avatars.root', $barrierDir.DIRECTORY_SEPARATOR.'avatars');
} else {
    http_response_code(503);
    exit;
}
$barrier = $request->headers->get('X-Identity-Test-Barrier');
$mode = $request->headers->get('X-Identity-Test-Mode');
// Activity-specific fault and barrier modes use the same guarded CI fixture.
if (is_string($mode) && str_starts_with($mode, 'activity-')
    && (getenv('GITHUB_ACTIONS') !== 'true' || $connection->getConfig('host') !== '127.0.0.1')) {
    http_response_code(503);
    exit;
}
// Customer fault and barrier modes are confined to the existing disposable CI fixture.
if (is_string($mode) && str_starts_with($mode, 'customer-')
    && (getenv('GITHUB_ACTIONS') !== 'true' || $connection->getConfig('host') !== '127.0.0.1'
        || (string) $connection->getConfig('port') !== '3306' || $connection->getConfig('unix_socket'))) {
    http_response_code(503);
    exit;
}
// Role fault and barrier modes are confined to the authorized CI database.
if (is_string($mode) && (str_contains($mode, 'roles-action-') || str_contains($mode, 'role-write')
    || str_contains($mode, 'role-insert') || str_contains($mode, 'role-delete') || str_contains($mode, 'role-grant')
    || str_contains($mode, 'role-generation') || str_contains($mode, 'role-session') || str_contains($mode, 'roles-deadlock')
    || str_starts_with($mode, 'roles-cache-') || $mode === 'roles-performance')
    && (getenv('GITHUB_ACTIONS') !== 'true' || $connection->getConfig('host') !== '127.0.0.1')) {
    http_response_code(503);
    exit;
}
// Shared local file cache exists only inside the guarded synthetic fixture.
if (is_string($mode) && str_starts_with($mode, 'roles-cache-')) {
    $cachePath = $barrierDir.DIRECTORY_SEPARATOR.'permission-cache';
    if (is_link($cachePath)) {
        http_response_code(503);
        exit;
    }
    config()->set('cache.stores.file.path', $cachePath);
    config()->set('cache.stores.file.lock_path', $cachePath);
    config()->set('permission.cache.store', 'file');
    $app->forgetInstance(PermissionRegistrar::class);
}
if (is_string($mode) && str_starts_with($mode, 'users-probe-') && $request->is('api/v1/users', 'api/v1/users/*')) {
    // Swap only the controller in the guarded test router for isolated boundary probes. The real route and middleware stack runs unchanged.
    $app->bind(UsersController::class, fn () => new UsersBoundaryProbe);
}

// The fixture FK is a reviewed test-only dependency, never a production table.
if (in_array($mode, ['users-action-protected-fk', 'hold-users-delete-fk'], true)) {
    $app->when(DeleteUserAction::class)->needs('$protectedUserForeignKeys')->give(['fixture_user_protected_fk']);
}

// Real Actions are not substituted. Only inject this synthetic postcommit
// cleanup fault in the guarded test router.
if ($mode === 'users-action-cleanup') {
    $app->bind(AvatarStorage::class, static fn () => new class extends AvatarStorage
    {
        public function delete(string $key): void
        {
            throw new RuntimeException('Synthetic avatar cleanup failure: '.$key);
        }
    });
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

// A separate worker observes native Spatie's shared cache, not an imitation.
// No sensitive payload, SQL or session information is returned.
Route::middleware('api')->get('/api/v1/__fixture-roles-cache/{role}', static function (int $role) use ($mode) {
    abort_unless($mode === 'roles-cache-probe' && getenv('GITHUB_ACTIONS') === 'true', 404);
    $cache = Cache::store('file');
    $key = config('permission.cache.key');
    $cachedBefore = $cache->has($key);
    $permission = app(PermissionRegistrar::class)->getPermissions([
        'name' => 'identity.roles.show', 'guard_name' => 'web',
    ])->sole();

    return response()->json([
        'cachedBefore' => $cachedBefore, 'cachedAfter' => $cache->has($key),
        'granted' => $permission->roles->contains(fn ($item) => (int) $item->id === $role),
        'worker' => getmypid(),
    ]);
});

// A fixture-only FK writer models a future owning module, without adding
// a real production route or table. This route runs only in this guarded router.
Route::middleware('api')->post('/api/v1/__fixture-user-link/{user}', static function (int $user) use ($mode, $pause) {
    try {
        DB::transaction(function () use ($user, $mode, $pause): void {
            if ($mode === 'hold-fk-deadlock') {
                // Test-only inversion: external fixture holds a parent S
                // lock and many undo entries, then waits for the manager.
                // Users holds manager and waits for this parent row. InnoDB
                // chooses the smaller read-only Users transaction as victim.
                DB::table('fixture_user_links')->insert(array_fill(0, 100, ['user_id' => $user]));
                $pause();
                DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->lockForUpdate()->first();

                return;
            }
            DB::table('fixture_user_links')->insert(['user_id' => $user]);
            if ($mode === 'hold-fk-insert') {
                $pause();
            }
        }, 1);
    } catch (QueryException $failure) {
        if ((int) ($failure->errorInfo[1] ?? 0) === 1452) {
            return response()->json(['fixture' => 'parent_missing'], 409);
        }
        throw $failure;
    }

    return response()->json(['fixture' => 'linked'], 201);
});
// Only the synthetic CI fixture can invert the manager/User lock order.
// The extra undo entries make the small Role transaction the deadlock victim.
// Reuse the existing throttle table; no fixture DDL or new reset is needed.
Route::middleware('api')->post('/api/v1/__fixture-roles-deadlock/{user}', static function (int $user) use ($mode, $pause) {
    abort_unless($mode === 'hold-roles-deadlock' && getenv('GITHUB_ACTIONS') === 'true', 404);
    DB::transaction(function () use ($user, $pause): void {
        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = ['scope' => 250, 'key_digest' => hash('sha256', 'synthetic-role-deadlock-'.$i),
                'failure_times' => '[]', 'blocked_until_ms' => null, 'expires_at_ms' => now()->getTimestampMs() + 60000];
        }
        DB::table('identity_login_throttles')->insert($rows);
        abort_if(DB::table('users')->where('id', $user)->lockForUpdate()->first(['id']) === null, 404);
        $pause();
        DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->lockForUpdate()->first(['id']);
    }, 1);

    return response()->json(['fixture' => 'committed'], 201);
});
if (in_array($mode, ['hold-avatar-before-read', 'hold-avatar-after-read', 'fault-avatar-stage'], true)) {
    $app->bind(AvatarStorage::class, static fn () => new class($mode, $pause) extends AvatarStorage
    {
        private bool $held = false;

        public function __construct(private string $mode, private Closure $pause) {}

        public function stage(#[SensitiveParameter] NormalizedAvatar $image): string
        {
            if ($this->mode === 'fault-avatar-stage') {
                throw new RuntimeException('Synthetic storage unavailable.');
            }

            return parent::stage($image);
        }

        public function read(string $key): NormalizedAvatar
        {
            if (! $this->held && $this->mode === 'hold-avatar-before-read') {
                $this->held = true;
                ($this->pause)();
            }
            $image = parent::read($key);
            if (! $this->held && $this->mode === 'hold-avatar-after-read') {
                $this->held = true;
                ($this->pause)();
            }

            return $image;
        }
    });
}
if ($mode === 'fault-users-hash') {
    // No mock framework in the cli-server bootstrap. Fail only the
    // actual hashing call; the other native HashManager methods are intact.
    Hash::swap(new class($app) extends HashManager
    {
        public function make(#[SensitiveParameter] $value, array $options = []): string
        {
            throw new RuntimeException('Synthetic hash failure.');
        }
    });
}
if (in_array($mode, ['fault-avatar-write', 'fault-avatar-close'], true)) {
    require __DIR__.'/avatar-io-faults.php';
}
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
    if ($request->is('api/v1/customers', 'api/v1/customers/*')) {
        $fields = [...$fields, 'name', 'mobile'];
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
    foreach (DB::table('users')->whereNotNull('avatar_key')->pluck('avatar_key') as $key) {
        $secrets[] = $key;
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

// Real Eloquent DML occurs before these test-only injected failures.
if ($mode === 'activity-fault-after-insert') {
    Activity::created(static fn () => throw new RuntimeException('Synthetic activity failure: synthetic-redaction-sentinel'));
}
if ($mode === 'activity-fault-after-update') {
    Activity::updated(static fn () => throw new RuntimeException('Synthetic activity failure: synthetic-redaction-sentinel'));
}
if ($mode === 'activity-fault-after-delete') {
    Activity::deleted(static fn () => throw new RuntimeException('Synthetic activity failure: synthetic-redaction-sentinel'));
}

// Guarded test-only model hooks fire after actual Customer DML.
if ($mode === 'customer-fault-after-insert') {
    Customer::created(static fn () => throw new RuntimeException('Synthetic Customer failure: synthetic-redaction-sentinel'));
}
if ($mode === 'customer-fault-after-update') {
    Customer::updated(static fn () => throw new RuntimeException('Synthetic Customer failure: synthetic-redaction-sentinel'));
}
if ($mode === 'customer-fault-other-constraint') {
    Customer::created(static function (Customer $customer): void {
        // A real named CHECK failure, not a synthetic duplicate or generic mapper.
        DB::table('customers')->where('id', $customer->id)->update(['is_active' => 2]);
    });
}

$count = 0;
$credentialReads = 0;
$usersTargetReads = 0;
$usersSensitiveReads = 0;
$usersCountReads = 0;
$usersPageReads = 0;
$usersRoleReads = 0;
$usersPageLimit = '';
$usersSnapshot = 0;
$rolesReads = 0;
$rolesTargetReads = 0;
$activityReads = 0;
$activityTargetReads = 0;
$activityWrites = 0;
$activityPageLimit = '';
$roleGenerationWrites = 0;
$roleSessionDeletes = 0;
$selectReads = 0;
$selectHasLimit = false;
$customerReads = 0;
$customerWrites = 0;
$timing = ['first_throttle_sql' => null, 'after_throttle_sql' => null, 'manager_start' => null];
$armed = true;
$restoreStatements = 0;
$staffTouches = 0;
$connection->beforeExecuting(static function (string $query) use ($mode, $pause, &$armed, &$count, &$credentialReads, &$usersTargetReads, &$usersSensitiveReads, &$usersCountReads, &$usersPageReads, &$usersRoleReads, &$usersPageLimit, &$usersSnapshot, &$rolesReads, &$rolesTargetReads, &$activityReads, &$activityTargetReads, &$activityWrites, &$activityPageLimit, &$roleGenerationWrites, &$roleSessionDeletes, &$selectReads, &$selectHasLimit, &$customerReads, &$customerWrites, &$secrets, &$timing, &$restoreStatements, &$staffTouches, $request, $connection): void {
    $count++;
    $sql = strtolower(str_replace(chr(96), '', $query));
    if (str_starts_with($sql, 'select') && str_contains($sql, 'from users') && str_contains($sql, 'avatar_key') && str_contains($sql, 'where users.id =')) {
        $usersTargetReads++;
    }
    if (str_contains($sql, 'synthetic_users_sensitive_probe')) {
        $usersSensitiveReads++;
    }
    if (str_starts_with($sql, 'select count(*) as aggregate from users')) {
        $usersCountReads++;
    }
    if (str_starts_with($sql, 'select') && str_contains($sql, 'from users') && str_contains($sql, 'order by users.id desc')) {
        $usersPageReads++;
        if (preg_match('/\blimit ([0-9]+)/', $sql, $match) === 1) {
            $usersPageLimit = $match[1];
        }
    }
    if (str_starts_with($sql, 'select') && str_contains($sql, 'from model_has_roles as mr') && str_contains($sql, 'mr.model_id in')) {
        $usersRoleReads++;
    }
    if ($sql === 'set transaction isolation level repeatable read, read only') {
        $usersSnapshot++;
    }
    if ($request->is('api/v1/selects') && preg_match('/\Aselect id, name from roles\b/', $sql) === 1) {
        $selectReads++;
        $selectHasLimit = $selectHasLimit || preg_match('/\blimit\b/', $sql) === 1;
    }
    if ($request->is('api/v1/roles', 'api/v1/roles/*') && str_starts_with($sql, 'select')) {
        if (preg_match('/\Aselect id from roles where\b/', $sql) === 1) {
            $rolesTargetReads++;
            $rolesReads++;
        } elseif (preg_match('/\Aselect (?:count\(\*\) as aggregate|roles\.id, roles\.name, roles\.is_active) from roles\b/', $sql) === 1
            || str_starts_with($sql, 'select mr.role_id, count(*) as aggregate from model_has_roles as mr')
            || str_starts_with($sql, 'select rp.role_id, count(*) as aggregate from role_has_permissions as rp')
            || str_starts_with($sql, 'select p.name, p.alias_name from role_has_permissions as rp')) {
            $rolesReads++;
        }
    }
    if ($request->is('api/v1/selects') && str_starts_with($sql, 'select p.name, p.alias_name from permissions as p')) {
        $selectReads++;
        $selectHasLimit = $selectHasLimit || preg_match('/\blimit\b/', $sql) === 1;
    }
    // Shape-only counters; never capture Customer SQL bindings or values.
    // beforeExecuting remains active even inside SensitiveQueryScope.
    $customerSelect = str_starts_with($sql, 'select') && preg_match('/\bfrom customers\b/', $sql) === 1;
    if ($customerSelect) {
        $customerReads++;
        if ($request->is('api/v1/selects')) {
            $selectReads++;
            $selectHasLimit = $selectHasLimit || preg_match('/\blimit\b/', $sql) === 1;
        }
    }
    $customerInsert = str_starts_with($sql, 'insert into customers ');
    $customerUpdate = str_starts_with($sql, 'update customers set ');
    if ($customerInsert || $customerUpdate || str_starts_with($sql, 'delete from customers ')) {
        $customerWrites++;
    }
    $activitySelect = str_starts_with($sql, 'select') && str_contains($sql, ' from activities');
    $activityAdmission = $activitySelect && preg_match('/\Aselect id from activities where\b/', $sql) === 1;
    $activityCount = $activitySelect && str_starts_with($sql, 'select count(*) as aggregate');
    $activityProjection = $activitySelect && str_starts_with($sql, 'select id, name, supports_hourly, supports_subscription, hourly_rate, monthly_price, is_active from activities');
    if ($activitySelect) {
        $activityReads++;
        if ($activityAdmission) {
            $activityTargetReads++;
        }
        if ($activityProjection && preg_match('/\blimit ([0-9]+)/', $sql, $match) === 1) {
            $activityPageLimit = $match[1];
        }
        if ($request->is('api/v1/selects') && str_starts_with($sql, 'select id, name from activities')) {
            $selectReads++;
            $selectHasLimit = $selectHasLimit || preg_match('/\blimit\b/', $sql) === 1;
        }
    }
    $activityInsert = str_starts_with($sql, 'insert into activities ');
    $activityUpdate = str_starts_with($sql, 'update activities set ');
    $activityDelete = str_starts_with($sql, 'delete from activities ');
    if ($activityInsert || $activityUpdate || $activityDelete) {
        $activityWrites++;
    }
    if ($mode === 'roles-performance') {
        if (str_starts_with($sql, 'update users set') && str_contains($sql, 'auth_version')) {
            $roleGenerationWrites++;
        }
        if (str_starts_with($sql, 'delete from sessions') && str_contains($sql, 'auth_user_id')) {
            $roleSessionDeletes++;
        }
    }
    $ns = hrtime(true);
    if (str_starts_with($sql, 'set session innodb_lock_wait_timeout =')) {
        $restoreStatements++;
        if ($restoreStatements === 1 && $mode === 'users-action-duplicate-create') {
            // Inject a real uniqueness collision AFTER validation and staging,
            // before BEGIN. Only synthetic fixtures in the guarded database.
            $statement = $connection->getPdo()->prepare('UPDATE users SET username = ? WHERE username = ?');
            $statement->execute(['Fixture.New', 'Fixture.Collision']);
        }
        if ($restoreStatements === 2 && in_array($mode, ['users-action-cache', 'roles-action-cache'], true)) {
            app()->forgetInstance(PermissionRegistrar::class);
            app()->bind(PermissionRegistrar::class, static fn () => throw new RuntimeException('Synthetic postcommit cache failure.'));
        }
        if ($restoreStatements === 2 && in_array($mode, ['fault-restore-after-commit', 'fault-restore-with-timeout', 'users-probe-mutation-restore', 'users-probe-mutation-rollback-restore', 'users-action-timeout-restore', 'roles-action-timeout-restore', 'activity-timeout-restore', 'customer-timeout-restore'], true)) {
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
    if (str_starts_with($sql, 'update sessions set auth_last_activity')) {
        $staffTouches++;
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

    $userWrite = str_starts_with($sql, 'update users set');
    $userInsert = str_starts_with($sql, 'insert into users');
    $userDelete = str_starts_with($sql, 'delete from users');
    $roleInsert = str_starts_with($sql, 'insert into model_has_roles');
    $roleCreate = str_starts_with($sql, 'insert into roles ');
    $roleWrite = str_starts_with($sql, 'update roles set ');
    $roleDelete = str_starts_with($sql, 'delete from roles ');
    $roleGrant = str_starts_with($sql, 'insert into role_has_permissions ');
    $roleGeneration = $userWrite && str_contains($sql, 'auth_version');
    if ($roleCreate && $mode === 'roles-action-duplicate') {
        // Same real transaction: force the named MySQL unique constraint
        // AFTER the service's collation precheck, before its native insert.
        $armed = false;
        $statement = $connection->getPdo()->prepare('INSERT INTO roles (name, guard_name, is_active) VALUES (?, ?, ?)');
        $statement->execute(['Fixture Constraint Collision', 'web', 1]);

        return;
    }
    if ($mode === 'customer-fault-before-insert' && $customerInsert) {
        $armed = false;
        throw new RuntimeException('Synthetic Customer failure: synthetic-redaction-sentinel');
    }
    if ($mode === 'customer-native-save-fault' && $request->attributes->get('customers_outcome') === 'committed'
        && ($nativeSave || $nativeInsert)) {
        $armed = false;
        throw new RuntimeException('Synthetic Customer session save failure: synthetic-redaction-sentinel');
    }
    if ($mode === 'activity-native-save-fault' && $request->attributes->get('activities_outcome') === 'committed'
        && ($nativeSave || $nativeInsert)) {
        $armed = false;
        throw new RuntimeException('Synthetic native save failure: synthetic-redaction-sentinel');
    }
    if (in_array($mode, ['users-action-final-save', 'roles-action-final-save'], true) && $request->attributes->get('staff_self_revoked') && ($nativeSave || $nativeInsert)) {
        $armed = false;
        throw new RuntimeException('Synthetic postcommit native save failure.');
    }
    $hit = match ($mode) {
        'hold-credentials', 'fault-report' => $credentialRead,
        'hold-manager', 'hold-manager-expire' => $managerLock,
        'hold-users-insert' => $userInsert,
        'activity-hold-page' => $activityProjection && $request->is('api/v1/activities'),
        'activity-hold-detail' => $activityProjection && $request->is('api/v1/activities/*'),
        'activity-hold-insert' => $activityInsert,
        'activity-hold-update', 'activity-performance-hold-update' => $activityUpdate,
        'activity-hold-delete' => $activityDelete,
        'activity-hold-manager' => $managerLock,
        'customer-hold-insert' => $customerInsert,
        'customer-hold-update', 'customer-performance-hold-update' => $customerUpdate,
        'hold-role-insert' => $roleCreate,
        'hold-role-write', 'fault-role-write' => $roleWrite,
        'hold-role-delete', 'fault-role-delete' => $roleDelete,
        'fault-role-grant' => $roleGrant,
        'fault-role-generation' => $roleGeneration,
        'fault-role-session' => $revoke,
        'hold-users-update' => $userWrite,
        'hold-users-delete-fk' => $userDelete,
        'fault-users-pivot' => $roleInsert,
        'fault-users-reference' => $userWrite && str_contains($sql, 'avatar_key'),
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
    if ($mode === 'hold-manager-expire') {
        Carbon::setTestNow(now()->addSecond());
    }
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
if (in_array($mode, ['observe-deadlock', 'users-performance', 'roles-performance', 'activity-performance', 'activity-performance-hold-update', 'roles-action-deadlock', 'roles-action-duplicate', 'roles-action-timeout-restore', 'activity-observe-mysql', 'activity-timeout-restore', 'customer-observe-mysql', 'customer-timeout-restore', 'customer-fault-other-constraint', 'customer-performance', 'customer-performance-hold-update'], true)) {
    $connection->getPdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [UsersObservedStatement::class, []]);
}
if ($mode === 'users-performance' && $request->isMethod('POST') && $request->is('api/v1/users')) {
    $connection->setPdo(new UsersMeasuredCommit($connection->getPdo()));
}
if ($mode === 'roles-performance' && $request->isMethod('PUT') && $request->is('api/v1/roles/*')) {
    $connection->setPdo(new UsersMeasuredCommit($connection->getPdo()));
}
if (in_array($mode, ['roles-cache-hold-commit', 'roles-cache-hold-rollback'], true) && $request->isMethod('PUT') && $request->is('api/v1/roles/*')) {
    $connection->setPdo(new RolesCacheBarrier($connection->getPdo(), $pause, $mode === 'roles-cache-hold-commit'));
}
if (in_array($mode, ['activity-performance', 'activity-performance-hold-update'], true)
    && ($request->is('api/v1/activities', 'api/v1/activities/*') || $request->is('api/v1/roles/*'))
    && in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'], true)) {
    $connection->setPdo(new UsersMeasuredCommit($connection->getPdo()));
}
if (in_array($mode, ['customer-performance', 'customer-performance-hold-update'], true)
    && ($request->is('api/v1/customers', 'api/v1/customers/*') || $request->is('api/v1/roles/*'))
    && in_array($request->getMethod(), ['POST', 'PUT'], true)) {
    $connection->setPdo(new UsersMeasuredCommit($connection->getPdo()));
}
$commitFault = null;
if (in_array($mode, ['fault-commit-ack', 'users-probe-mutation-ack', 'users-action-commit-ack', 'roles-action-commit-ack', 'activity-commit-ack', 'customer-commit-ack'], true)) {
    $commitFault = new AuthenticationCommitFault($connection->getPdo(), $connection);
    $connection->setPdo($commitFault);
}

if (in_array($mode, ['users-performance', 'roles-performance', 'activity-performance', 'activity-performance-hold-update', 'customer-performance', 'customer-performance-hold-update'], true)) {
    memory_reset_peak_usage();
}
$response = $kernel->handle($request);
if (in_array($mode, ['users-performance', 'roles-performance', 'activity-performance', 'activity-performance-hold-update', 'customer-performance', 'customer-performance-hold-update'], true)) {
    $response->headers->set('X-Test-Php-Peak-Bytes', (string) memory_get_peak_usage(true));
    $usage = getrusage();
    if (is_array($usage) && isset($usage['ru_maxrss'])) {
        $response->headers->set('X-Test-Process-Peak-Rss', (string) $usage['ru_maxrss']);
    }
    foreach (['lock_wait_ms' => 'Lock-Wait-Ms', 'lock_span_ms' => 'Lock-Span-Ms', 'commit_ms' => 'Commit-Ms'] as $attribute => $header) {
        $value = $request->attributes->get('_test_users_'.$attribute);
        if (is_float($value)) {
            $prefix = match ($mode) {
                'roles-performance' => 'X-Test-Roles-',
                'activity-performance', 'activity-performance-hold-update' => 'X-Test-Activity-',
                'customer-performance', 'customer-performance-hold-update' => 'X-Test-Customer-',
                default => 'X-Test-Users-',
            };
            $response->headers->set($prefix.$header, (string) round($value, 3));
        }
    }
}
if ($commitFault !== null) {
    $response->headers->set('X-Test-Actual-Commit-Count', (string) $commitFault->commits);
}
$response->headers->set('X-Test-Query-Count', (string) $count);
if ($request->attributes->has('_test_roles_mysql_code')) {
    $response->headers->set('X-Test-Roles-MySQL-Code', (string) $request->attributes->get('_test_roles_mysql_code'));
    $response->headers->set('X-Test-Roles-MySQL-Count', (string) $request->attributes->get('_test_roles_mysql_count'));
}
$response->headers->set('X-Test-Users-Deadlock-Count', (string) $request->attributes->get('_test_users_deadlock_count', 0));
if ($request->attributes->has('_test_users_deadlock_code')) {
    $response->headers->set('X-Test-Users-Deadlock-Code', (string) $request->attributes->get('_test_users_deadlock_code'));
}
$response->headers->set('X-Test-Users-Touches', (string) $staffTouches);
$response->headers->set('X-Test-Users-Self-Revoked', $request->attributes->get('staff_self_revoked') ? '1' : '0');
$outcome = $request->attributes->get('identity_users_outcome');
if (is_string($outcome)) {
    $response->headers->set('X-Test-Users-Outcome', $outcome);
}
$rolesOutcome = $request->attributes->get('identity_roles_outcome');
if (is_string($rolesOutcome)) {
    $response->headers->set('X-Test-Roles-Outcome', $rolesOutcome);
}
if ($diagnostic !== null) {
    $response->headers->set('X-Test-Exception-Class', $diagnostic);
}
$response->headers->set('X-Test-Credential-Reads', (string) $credentialReads);
$response->headers->set('X-Test-Users-Target-Reads', (string) $usersTargetReads);
$response->headers->set('X-Test-Users-Sensitive-Reads', (string) $usersSensitiveReads);
$response->headers->set('X-Test-Users-Count-Reads', (string) $usersCountReads);
$response->headers->set('X-Test-Users-Page-Reads', (string) $usersPageReads);
$response->headers->set('X-Test-Users-Role-Reads', (string) $usersRoleReads);
$response->headers->set('X-Test-Users-Page-Limit', $usersPageLimit);
$response->headers->set('X-Test-Users-Snapshot', (string) $usersSnapshot);
$response->headers->set('X-Test-Roles-Reads', (string) $rolesReads);
$response->headers->set('X-Test-Roles-Target-Reads', (string) $rolesTargetReads);
$response->headers->set('X-Test-Activity-Reads', (string) $activityReads);
$response->headers->set('X-Test-Activity-Target-Reads', (string) $activityTargetReads);
$response->headers->set('X-Test-Activity-Writes', (string) $activityWrites);
if ($request->attributes->has('_test_activity_mysql_code')) {
    $response->headers->set('X-Test-Activity-MySQL-Code', (string) $request->attributes->get('_test_activity_mysql_code'));
    $response->headers->set('X-Test-Activity-MySQL-Count', (string) $request->attributes->get('_test_activity_mysql_count'));
}
$response->headers->set('X-Test-Activity-Timeout-Statements', (string) $restoreStatements);
$response->headers->set('X-Test-Activity-Page-Limit', $activityPageLimit);
$activityOutcome = $request->attributes->get('activities_outcome');
if (is_string($activityOutcome)) {
    $response->headers->set('X-Test-Activity-Outcome', $activityOutcome);
}
$response->headers->set('X-Test-Roles-Generation-Writes', (string) $roleGenerationWrites);
$response->headers->set('X-Test-Roles-Session-Deletes', (string) $roleSessionDeletes);
$response->headers->set('X-Test-Select-Reads', (string) $selectReads);
$response->headers->set('X-Test-Select-Has-Limit', $selectHasLimit ? '1' : '0');
$response->headers->set('X-Test-Customer-Reads', (string) $customerReads);
$response->headers->set('X-Test-Customer-Writes', (string) $customerWrites);
$customerOutcome = $request->attributes->get('customers_outcome');
if (is_string($customerOutcome)) {
    $response->headers->set('X-Test-Customer-Outcome', $customerOutcome);
}
if ($request->attributes->has('_test_customer_mysql_code')) {
    $response->headers->set('X-Test-Customer-MySQL-Code', (string) $request->attributes->get('_test_customer_mysql_code'));
    $response->headers->set('X-Test-Customer-MySQL-Count', (string) $request->attributes->get('_test_customer_mysql_count'));
}
$response->headers->set('X-Test-Customer-Timeout-Statements', (string) $restoreStatements);
foreach (['state', 'file_outcome', 'calls', 'wait'] as $field) {
    $value = $request->attributes->get('_test_mutation_'.$field);
    if (is_string($value)) {
        $response->headers->set('X-Test-Mutation-'.str_replace('_', '-', $field), $value);
    }
}
if ($request->attributes->get('_test_avatar_validated')) {
    $response->headers->set('X-Test-Avatar-Validated', '1');
}
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
    $response->headers->set('X-Test-Users-Outcome-Reported', collect($logs)->contains(fn ($log) => $log[0] === 'API operation failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')
        && is_string($outcome) && ($log[1]['mutation_outcome'] ?? null) === $outcome) ? '1' : '0');
    $response->headers->set('X-Test-Roles-Outcome-Reported', collect($logs)->contains(fn ($log) => $log[0] === 'API operation failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')
        && is_string($rolesOutcome) && ($log[1]['mutation_outcome'] ?? null) === $rolesOutcome) ? '1' : '0');
    $response->headers->set('X-Test-Activity-Outcome-Reported', collect($logs)->contains(fn ($log) => $log[0] === 'API operation failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')
        && is_string($activityOutcome) && ($log[1]['mutation_outcome'] ?? null) === $activityOutcome) ? '1' : '0');
    $response->headers->set('X-Test-Customer-Outcome-Reported', collect($logs)->contains(fn ($log) => $log[0] === 'API operation failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')
        && is_string($customerOutcome) && ($log[1]['mutation_outcome'] ?? null) === $customerOutcome) ? '1' : '0');
    $response->headers->set('X-Test-Customer-Restore-Warning', collect($logs)->contains(fn ($log) => $log[0] === 'Customer lock timeout restoration failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')) ? '1' : '0');
    $customerVisible = collect($queries)->contains(fn ($query) => preg_match('/\bcustomers\b/i', $query[0]) === 1)
        || collect($queryLog)->contains(fn ($query) => preg_match('/\bcustomers\b/i', $query['query']) === 1);
    $response->headers->set('X-Test-Customer-Query-Visible', $customerVisible ? '1' : '0');
    $response->headers->set('X-Test-Activity-Restore-Warning', collect($logs)->contains(fn ($log) => $log[0] === 'Activity lock timeout restoration failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')) ? '1' : '0');
    $response->headers->set('X-Test-Roles-Restore-Warning', collect($logs)->contains(fn ($log) => $log[0] === 'Roles lock timeout restoration failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')) ? '1' : '0');
    $response->headers->set('X-Test-Roles-Cache-Warning', collect($logs)->contains(fn ($log) => $log[0] === 'Roles permission cache invalidation failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')) ? '1' : '0');
    $response->headers->set('X-Test-Users-Restore-Warning', collect($logs)->contains(fn ($log) => $log[0] === 'Users lock timeout restoration failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')) ? '1' : '0');
    $response->headers->set('X-Test-Users-Cache-Warning', collect($logs)->contains(fn ($log) => $log[0] === 'Users permission cache invalidation failed.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')) ? '1' : '0');
    $response->headers->set('X-Test-Avatar-Cleanup-Warning', collect($logs)->contains(fn ($log) => $log[0] === 'Avatar lifecycle retained file.'
        && ($log[1]['request_id'] ?? null) === $response->headers->get('X-Request-ID')
        && ($log[1]['outcome'] ?? null) === 'cleanup_failed') ? '1' : '0');
    $response->headers->set('X-Test-Error-Report-Count', (string) collect($logs)->filter(fn ($log) => $log[0] === 'API operation failed.')->count());
    $response->headers->set('X-Test-Restore-Failure', collect($logs)->contains(fn ($log) => $log[0] === 'Login lock timeout restoration failed.') ? '1' : '0');
    $connection->disableQueryLog();
    foreach (DB::table('users')->pluck('password') as $hash) {
        $secrets[] = $hash;
    }
    foreach (DB::table('users')->whereNotNull('avatar_key')->pluck('avatar_key') as $key) {
        $secrets[] = $key;
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
