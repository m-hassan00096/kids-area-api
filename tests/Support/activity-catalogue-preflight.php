<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $failure): void {
    fwrite(STDERR, 'Activity catalogue preflight aborted: '.$failure::class.' at '.basename($failure->getFile()).':'.$failure->getLine().PHP_EOL);
    exit(1);
});

function requireActivityPreflight(bool $condition, string $reason): void
{
    if (! $condition) {
        fwrite(STDERR, $reason.PHP_EOL);
        throw new RuntimeException($reason);
    }
}

function emitActivityPreflight(string $label, array $data): void
{
    echo $label.' '.json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
}

function activityPreflightRejected(Closure $operation, int $expectedCode, ?string $constraint = null): void
{
    try {
        $operation();
    } catch (QueryException $failure) {
        requireActivityPreflight((int) ($failure->errorInfo[1] ?? 0) === $expectedCode, 'Unexpected native MySQL rejection.');
        if ($constraint !== null) {
            requireActivityPreflight(str_contains((string) ($failure->errorInfo[2] ?? ''), $constraint), 'Unexpected rejected constraint.');
        }

        return;
    }

    requireActivityPreflight(false, 'MySQL accepted a deliberately invalid fixture operation.');
}

$db = DB::connection();
requireActivityPreflight(getenv('GITHUB_ACTIONS') === 'true'
    && getenv('ACTIVITY_PREFLIGHT_ISOLATED_JOB') === '1'
    && $app->environment('testing') && $db->getDriverName() === 'mysql'
    && $db->getConfig('host') === '127.0.0.1' && (string) $db->getConfig('port') === '3306'
    && $db->getDatabaseName() === 'kids_area_test' && $db->getConfig('username') === 'kids_area_ci'
    && getenv('DB_URL') === '' && ! $db->getConfig('unix_socket')
    && config('permission.teams') === false, 'Unsafe Activity preflight environment.');

$server = $db->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS principal, VERSION() AS version, @@default_storage_engine AS engine, @@SESSION.sql_mode AS sql_mode, @@SESSION.time_zone AS time_zone, @@system_time_zone AS system_time_zone, TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS utc_offset, @@character_set_connection AS connection_charset, @@collation_connection AS connection_collation');
requireActivityPreflight($server->db === 'kids_area_test' && str_starts_with($server->principal, 'kids_area_ci@')
    && preg_match('/\A8\.[0-9]+\.[0-9]+/', $server->version, $version) === 1
    && version_compare($version[0], '8.0.18', '>=')
    && $server->engine === 'InnoDB'
    && (str_contains($server->sql_mode, 'STRICT_TRANS_TABLES') || str_contains($server->sql_mode, 'STRICT_ALL_TABLES'))
    && $server->utc_offset === '00:00:00' && config('app.timezone') === 'UTC'
    && $server->connection_charset === 'utf8mb4', 'Unsafe or incompatible Activity preflight server.');

$collation = $db->selectOne("SELECT COLLATION_NAME AS name, CHARACTER_SET_NAME AS charset, PAD_ATTRIBUTE AS pad FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_0900_as_ci'");
requireActivityPreflight($collation !== null && $collation->charset === 'utf8mb4' && $collation->pad === 'NO PAD', 'Approved collation unavailable or incompatible.');
requireActivityPreflight((int) $db->selectOne('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->total === 0,
    'Preflight requires a newly created empty service database; it never resets one.');
emitActivityPreflight('ACTIVITY_PREFLIGHT_ENV', [
    'mysql' => $server->version,
    'engine' => $server->engine,
    'strictMode' => $server->sql_mode,
    'sessionTimezone' => $server->time_zone,
    'systemTimezone' => $server->system_time_zone,
    'utcOffset' => $server->utc_offset,
    'connectionCharset' => $server->connection_charset,
    'connectionCollation' => $server->connection_collation,
    'approvedCollation' => $collation,
]);

// Existing additive baseline migrations only, in this dedicated service.
requireActivityPreflight(Artisan::call('migrate', ['--force' => true]) === 0, 'Existing baseline migration failed.');
$expectedMigrations = [
    '2026_11_25_000001_create_users_table',
    '2026_11_25_000002_create_permission_tables',
    '2026_11_25_000003_enforce_single_staff_role',
    '2026_11_25_000004_add_auth_version_to_users',
    '2026_11_25_000005_create_sessions_table',
    '2026_11_25_000006_create_identity_login_throttles_table',
    '2026_11_25_000007_add_avatar_key_to_users',
    '2026_11_26_000008_remove_alias_name_from_roles',
];
$migrations = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
requireActivityPreflight($migrations === $expectedMigrations, 'Unexpected baseline migration inventory.');
$baselineTables = ['identity_login_throttles', 'migrations', 'model_has_permissions', 'model_has_roles', 'permissions', 'role_has_permissions', 'roles', 'sessions', 'users'];
$engines = $db->table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')->orderBy('TABLE_NAME')->pluck('ENGINE', 'TABLE_NAME')->all();
requireActivityPreflight(array_keys($engines) === $baselineTables && count(array_unique(array_values($engines))) === 1
    && reset($engines) === 'InnoDB', 'Unexpected baseline table inventory or engine.');
$before = [];
foreach ($baselineTables as $table) {
    $before[$table] = $db->table($table)->count();
}
emitActivityPreflight('ACTIVITY_PREFLIGHT_BASELINE', ['migrations' => $migrations, 'engines' => $engines, 'counts' => $before]);

// Non-temporary isolated fixture: test real InnoDB constraints without installing
// an Activities migration or touching any existing table. Service teardown owns
// disposal; there is no DROP, DELETE, truncate or migrate:fresh in this script.
$db->statement(<<<'SQL'
    CREATE TABLE activities_preflight_fixture (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_ci NOT NULL,
        supports_hourly TINYINT(1) NOT NULL,
        supports_subscription TINYINT(1) NOT NULL,
        hourly_rate DECIMAL(12,2) NULL DEFAULT NULL,
        monthly_price DECIMAL(12,2) NULL DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        UNIQUE KEY uq_activities_name (name),
        CONSTRAINT chk_activities_flags CHECK (supports_hourly IN (0,1) AND supports_subscription IN (0,1) AND is_active IN (0,1)),
        CONSTRAINT chk_activities_billing CHECK (supports_hourly = 1 OR supports_subscription = 1),
        CONSTRAINT chk_activities_hourly_rate CHECK ((supports_hourly = 1 AND hourly_rate IS NOT NULL AND hourly_rate > 0) OR (supports_hourly = 0 AND hourly_rate IS NULL)),
        CONSTRAINT chk_activities_monthly_price CHECK ((supports_subscription = 1 AND monthly_price IS NOT NULL AND monthly_price > 0) OR (supports_subscription = 0 AND monthly_price IS NULL)),
        CONSTRAINT chk_activities_name CHECK (CHAR_LENGTH(TRIM(name)) > 0)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    SQL);
$fixture = 'activities_preflight_fixture';
$metadata = $db->select("SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLLATION_NAME AS collation_name, COLUMN_DEFAULT AS default_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities_preflight_fixture' ORDER BY ORDINAL_POSITION");
$checks = $db->select("SELECT CONSTRAINT_NAME AS name, ENFORCED AS enforced FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities_preflight_fixture' AND CONSTRAINT_TYPE = 'CHECK' ORDER BY CONSTRAINT_NAME");
$indexes = $db->select("SELECT INDEX_NAME AS name, COLUMN_NAME AS column_name, NON_UNIQUE AS non_unique FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities_preflight_fixture' ORDER BY INDEX_NAME, SEQ_IN_INDEX");
requireActivityPreflight(count($metadata) === 9 && count($checks) === 5 && count($indexes) === 2
    && collect($checks)->every(fn ($check) => $check->enforced === 'YES')
    && collect($indexes)->every(fn ($index) => (int) $index->non_unique === 0), 'Fixture metadata/constraints unexpected.');
emitActivityPreflight('ACTIVITY_PREFLIGHT_DDL', ['columns' => $metadata, 'checks' => $checks, 'indexes' => $indexes]);

$defaults = ['supports_hourly' => 1, 'supports_subscription' => 0, 'hourly_rate' => '60.00', 'monthly_price' => null, 'is_active' => 1];
$insert = static fn (string $name, array $fields = []) => DB::table($fixture)->insertGetId(array_replace(['name' => $name], $defaults, $fields));
$rows = [];
$expectedMismatch = [];
$observations = [
    ['english-case', 'Swimming', 'swimming', true, false],
    ['trimmed-edges', Str::trim(' Swimming '), 'Swimming', true, false],
    ['arabic-identical', 'ألعاب الأطفال', 'ألعاب الأطفال', true, false],
    ['english-accent', 'Cafe', 'Café', false, false],
    ['arabic-diacritic', 'الرسم', 'الرَسم', false, false],
    ['internal-spaces', 'كرة قدم', 'كرة  قدم', false, false],
    ['alef-hamza', 'ا', 'أ', null, true],
    ['alef-hamza-below', 'ا', 'إ', null, true],
    ['alef-madda', 'ا', 'آ', null, true],
    ['yeh-maqsura', 'ي', 'ى', null, true],
    ['heh-teh-marbuta', 'ه', 'ة', null, true],
    ['tatweel', 'الرسم', 'الـرسم', null, true],
    ['canonical-accent', 'Café', "Cafe\u{0301}", null, false],
    ['mixed-script', 'سباحة Swim', 'سباحة SWIM', true, false],
    ['supplementary', '🎨', '🎭', null, true],
    ['nbsp-internal', 'كرة قدم', "كرة\u{00A0}قدم", null, true],
    ['trailing-space-db-only', 'Swimming', 'Swimming ', false, false],
    ['unicode-trim', Str::trim("\u{00A0}Swimming\u{2003}"), 'Swimming', true, false],
];
$review = [];
foreach ($observations as [$label, $left, $right, $expected, $flagEqual]) {
    $equal = $db->selectOne('SELECT CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_as_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_as_ci AS eq', [$left, $right]);
    $like = $db->selectOne('SELECT CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_as_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_as_ci AS matches_name', [$left, $right]);
    $isEqual = (bool) $equal->eq;
    $duplicate = false;
    $db->beginTransaction();
    try {
        $firstId = $insert($left, ['is_active' => 0]);
        try {
            $insert($right);
        } catch (QueryException $failure) {
            requireActivityPreflight((int) ($failure->errorInfo[1] ?? 0) === 1062
                && str_contains((string) ($failure->errorInfo[2] ?? ''), 'uq_activities_name'), 'Unexpected collation fixture error.');
            $duplicate = true;
        }
        requireActivityPreflight($isEqual === $duplicate, 'Collation equality and unique index disagree.');
        requireActivityPreflight($db->table($fixture)->where('id', $firstId)->value('name') === $left, 'Stored spelling unexpectedly changed.');
    } finally {
        $db->rollBack();
    }
    $rows[] = ['case' => $label, 'left' => $left, 'right' => $right, 'equal' => $isEqual, 'like' => (bool) $like->matches_name, 'uniqueConflict' => $duplicate, 'expected' => $expected];
    if ($expected !== null && $expected !== $isEqual) {
        $expectedMismatch[] = $label;
    }
    if ($flagEqual && $isEqual) {
        $review[] = $label;
    }
}
emitActivityPreflight('ACTIVITY_PREFLIGHT_COLLATION', $rows);

// SQL literal LIKE with an explicit escape character, not user wildcard syntax.
$literal = [];
foreach (['%', '_', '!', '\\', 'a,b', 'سباحة'] as $needle) {
    $pattern = '%'.strtr($needle, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    $hit = $db->selectOne("SELECT CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_as_ci LIKE ? ESCAPE '!' AS matched", ['before '.$needle.' after', $pattern]);
    $miss = $db->selectOne("SELECT CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_as_ci LIKE ? ESCAPE '!' AS matched", ['unrelated text', $pattern]);
    requireActivityPreflight((bool) $hit->matched && ! (bool) $miss->matched, 'Literal substring search failed.');
    $literal[] = ['needle' => $needle, 'matchedLiteral' => true, 'unrelatedRejected' => true];
}
emitActivityPreflight('ACTIVITY_PREFLIGHT_LITERAL_SEARCH', $literal);

$db->beginTransaction();
try {
    $valid = [
        ['hourly', []],
        ['subscription', ['supports_hourly' => 0, 'supports_subscription' => 1, 'hourly_rate' => null, 'monthly_price' => '0.01']],
        ['both', ['supports_subscription' => 1, 'hourly_rate' => '9999999999.99', 'monthly_price' => '60.50']],
    ];
    foreach ($valid as [$name, $fields]) {
        $id = $insert($name, $fields);
        $row = $db->table($fixture)->where('id', $id)->first();
        $expected = array_replace($defaults, $fields);
        requireActivityPreflight($row->hourly_rate === $expected['hourly_rate'] && $row->monthly_price === $expected['monthly_price'], 'Unexpected exact DECIMAL roundtrip.');
    }
    $invalid = [
        ['no-mode', ['supports_hourly' => 0, 'hourly_rate' => null], 3819, 'chk_activities_billing'],
        ['hourly-null', ['hourly_rate' => null], 3819, 'chk_activities_hourly_rate'],
        ['hourly-zero', ['hourly_rate' => '0.00'], 3819, 'chk_activities_hourly_rate'],
        ['hourly-negative', ['hourly_rate' => '-0.01'], 3819, 'chk_activities_hourly_rate'],
        ['disabled-hourly-price', ['supports_hourly' => 0, 'supports_subscription' => 1, 'monthly_price' => '1.00'], 3819, 'chk_activities_hourly_rate'],
        ['monthly-null', ['supports_subscription' => 1], 3819, 'chk_activities_monthly_price'],
        ['monthly-zero', ['supports_subscription' => 1, 'monthly_price' => '0.00'], 3819, 'chk_activities_monthly_price'],
        ['monthly-negative', ['supports_subscription' => 1, 'monthly_price' => '-0.01'], 3819, 'chk_activities_monthly_price'],
        ['disabled-monthly-price', ['monthly_price' => '1.00'], 3819, 'chk_activities_monthly_price'],
        ['active-flag', ['is_active' => 2], 3819, 'chk_activities_flags'],
        ['hourly-flag', ['supports_hourly' => 2], 3819, null],
        ['subscription-flag', ['supports_subscription' => 2], 3819, null],
        ['empty-name', ['name' => ''], 3819, 'chk_activities_name'],
        ['space-name', ['name' => '   '], 3819, 'chk_activities_name'],
        ['name-overflow', ['name' => str_repeat('س', 256)], 1406, null],
        ['rate-overflow', ['hourly_rate' => '10000000000.00'], 1264, null],
        ['null-flag', ['supports_hourly' => null], 1048, null],
    ];
    $rejected = [];
    foreach ($invalid as [$label, $fields, $code, $constraint]) {
        activityPreflightRejected(fn () => $insert($label, $fields), $code, $constraint);
        $rejected[] = ['case' => $label, 'mysqlCode' => $code, 'constraint' => $constraint];
    }
    $first = $insert('Rename source');
    $second = $insert('Rename target');
    activityPreflightRejected(fn () => $db->table($fixture)->where('id', $second)->update(['name' => 'rename SOURCE']), 1062, 'uq_activities_name');
    activityPreflightRejected(fn () => $db->table($fixture)->where('id', $first)->update(['hourly_rate' => null]), 3819, 'chk_activities_hourly_rate');
    requireActivityPreflight($db->table($fixture)->where('id', $second)->value('name') === 'Rename target'
        && $db->table($fixture)->where('id', $first)->value('hourly_rate') === '60.00', 'Rejected updates changed stored values.');
    requireActivityPreflight($db->table($fixture)->where('id', $first)->update(['name' => 'RENAME SOURCE']) === 1, 'Same-record display case update failed.');
    emitActivityPreflight('ACTIVITY_PREFLIGHT_CONSTRAINTS', ['validModes' => count($valid), 'rejectedInserts' => $rejected, 'duplicateRenameRejected' => true, 'invalidUpdateRejected' => true, 'sameRecordCaseUpdate' => true]);
} finally {
    $db->rollBack();
}

requireActivityPreflight($db->table($fixture)->count() === 0, 'Fixture rows survived transaction rollback.');
foreach ($baselineTables as $table) {
    requireActivityPreflight($db->table($table)->count() === $before[$table], 'Baseline row count changed during probe.');
}

emitActivityPreflight('ACTIVITY_PREFLIGHT_RESULT', [
    'explicitExpectationMismatches' => $expectedMismatch,
    'unapprovedEquivalencesForReview' => $review,
    'collationPairs' => count($rows),
    'literalSearchCases' => count($literal),
    'fixtureRowsAfterRollback' => 0,
    'baselineCountsUnchanged' => true,
    'scope' => 'test fixture only; no production schema, API or feature acceptance evidence',
]);
requireActivityPreflight($expectedMismatch === [] && $review === [], 'Collation observations require review before affected implementation.');
