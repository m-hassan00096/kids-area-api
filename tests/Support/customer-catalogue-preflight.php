<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $failure): void {
    // This is a synthetic CI probe. Do not print exception messages or bindings.
    fwrite(STDERR, 'Customer preflight aborted: '.$failure::class.' at '.basename($failure->getFile()).':'.$failure->getLine().PHP_EOL);
    exit(1);
});

function requireCustomerPreflight(bool $condition, string $reason): void
{
    if (! $condition) {
        fwrite(STDERR, $reason.PHP_EOL);
        throw new RuntimeException($reason);
    }
}

function emitCustomerPreflight(string $label, array $data): void
{
    echo $label.' '.json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
}

function customerPreflightRejected(string $label, Closure $operation, int|array $code, ?string $constraint = null): void
{
    $expected = (array) $code;
    try {
        $operation();
    } catch (QueryException $failure) {
        $actual = (int) ($failure->errorInfo[1] ?? 0);
        $state = (string) ($failure->errorInfo[0] ?? '');
        $matchesConstraint = $constraint === null || str_contains((string) ($failure->errorInfo[2] ?? ''), $constraint);
        // Only fixed synthetic case labels and native error metadata are logged.
        // Never print the exception, SQL, bindings or raw server error message.
        emitCustomerPreflight('CUSTOMER_PREFLIGHT_REJECTION', [
            'case' => $label,
            'expectedCodes' => $expected,
            'actualCode' => $actual,
            'sqlState' => $state,
            'constraint' => $constraint,
            'constraintMatched' => $matchesConstraint,
        ]);
        requireCustomerPreflight(in_array($actual, $expected, true), 'Unexpected MySQL rejection code for '.$label.'.');
        requireCustomerPreflight($matchesConstraint, 'Unexpected rejected constraint for '.$label.'.');
        if ($label === 'non_ascii_mobile') {
            // Both errors are native charset/column conversion rejections.
            // Do not accept unrelated errors or an actual successful write.
            requireCustomerPreflight($state === 'HY000', 'Unexpected SQLSTATE for non-ASCII mobile.');
        }

        return;
    }

    emitCustomerPreflight('CUSTOMER_PREFLIGHT_UNEXPECTED_ACCEPT', ['case' => $label]);
    requireCustomerPreflight(false, 'MySQL accepted deliberately invalid Customer fixture data for '.$label.'.');
}

$db = DB::connection();
requireCustomerPreflight(getenv('GITHUB_ACTIONS') === 'true'
    && getenv('CUSTOMER_PREFLIGHT_ISOLATED_JOB') === '1'
    && getenv('DB_URL') === '' && ! $db->getConfig('unix_socket')
    && $app->environment('testing') && $db->getDriverName() === 'mysql'
    && $db->getConfig('host') === '127.0.0.1' && (string) $db->getConfig('port') === '3306'
    && $db->getDatabaseName() === 'kids_area_test' && $db->getConfig('username') === 'kids_area_ci'
    && config('permission.teams') === false, 'Unsafe Customer preflight environment.');

$server = $db->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS principal, VERSION() AS version, @@default_storage_engine AS engine, @@SESSION.sql_mode AS sql_mode, TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS utc_offset, @@character_set_connection AS connection_charset');
requireCustomerPreflight($server->db === 'kids_area_test' && str_starts_with($server->principal, 'kids_area_ci@')
    && preg_match('/\A8\.[0-9]+\.[0-9]+/', $server->version, $version) === 1
    && version_compare($version[0], '8.0.16', '>=')
    && $server->engine === 'InnoDB'
    && (str_contains($server->sql_mode, 'STRICT_TRANS_TABLES') || str_contains($server->sql_mode, 'STRICT_ALL_TABLES'))
    && $server->utc_offset === '00:00:00' && config('app.timezone') === 'UTC'
    && $server->connection_charset === 'utf8mb4', 'Unsafe or incompatible Customer preflight server.');
requireCustomerPreflight((int) $db->selectOne('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->total === 0,
    'Customer preflight requires a new empty service database; it never resets one.');

$collations = $db->table('information_schema.COLLATIONS')->whereIn('COLLATION_NAME', ['ascii_bin', 'utf8mb4_unicode_ci'])
    ->orderBy('COLLATION_NAME')->pluck('CHARACTER_SET_NAME', 'COLLATION_NAME')->all();
requireCustomerPreflight($collations === ['ascii_bin' => 'ascii', 'utf8mb4_unicode_ci' => 'utf8mb4'], 'Approved Customer collations unavailable.');
emitCustomerPreflight('CUSTOMER_PREFLIGHT_ENV', [
    'mysql' => $server->version,
    'engine' => $server->engine,
    'strictMode' => $server->sql_mode,
    'utcOffset' => $server->utc_offset,
    'collations' => $collations,
]);

// Migrate only the existing reviewed baseline in this isolated empty service.
$baselinePaths = [
    'Modules/Identity/database/migrations/2026_11_25_000001_create_users_table.php',
    'Modules/Identity/database/migrations/2026_11_25_000002_create_permission_tables.php',
    'Modules/Identity/database/migrations/2026_11_25_000003_enforce_single_staff_role.php',
    'Modules/Identity/database/migrations/2026_11_25_000004_add_auth_version_to_users.php',
    'Modules/Identity/database/migrations/2026_11_25_000005_create_sessions_table.php',
    'Modules/Identity/database/migrations/2026_11_25_000006_create_identity_login_throttles_table.php',
    'Modules/Identity/database/migrations/2026_11_25_000007_add_avatar_key_to_users.php',
    'Modules/Identity/database/migrations/2026_11_26_000008_remove_alias_name_from_roles.php',
    'Modules/Identity/database/migrations/2026_11_26_000009_create_identity_feature_provisions_table.php',
    'Modules/Activities/database/migrations/2026_11_26_000010_create_activities_table.php',
];
requireCustomerPreflight(Artisan::call('migrate', ['--force' => true, '--path' => $baselinePaths]) === 0, 'Existing baseline migration failed.');
$expectedMigrations = array_map(static fn (string $path): string => basename($path, '.php'), $baselinePaths);
$migrations = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
requireCustomerPreflight($migrations === $expectedMigrations, 'Unexpected existing migration inventory.');
$baselineTables = ['activities', 'identity_feature_provisions', 'identity_login_throttles', 'migrations', 'model_has_permissions', 'model_has_roles', 'permissions', 'role_has_permissions', 'roles', 'sessions', 'users'];
$engines = $db->table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')->orderBy('TABLE_NAME')->pluck('ENGINE', 'TABLE_NAME')->all();
requireCustomerPreflight(array_keys($engines) === $baselineTables && array_unique(array_values($engines)) === ['InnoDB'], 'Unexpected existing schema.');
$snapshot = static function () use ($db, $baselineTables): array {
    $state = [];
    foreach ($baselineTables as $table) {
        // The names are a fixed local allowlist; baseline is empty except migrations.
        $state[$table] = [
            'ddl' => (array) $db->selectOne('SHOW CREATE TABLE '.chr(96).$table.chr(96)),
            'count' => $db->table($table)->count(),
        ];
    }
    $state['migrationRows'] = $db->table('migrations')->orderBy('id')->get()->all();

    return $state;
};
$before = $snapshot();
requireCustomerPreflight($before['migrations']['count'] === 10, 'Expected ten historical migrations.');
foreach ($baselineTables as $table) {
    if ($table !== 'migrations') {
        requireCustomerPreflight($before[$table]['count'] === 0, 'Expected empty baseline business tables.');
    }
}
emitCustomerPreflight('CUSTOMER_PREFLIGHT_BASELINE', ['migrations' => $migrations, 'engines' => $engines, 'businessRows' => 0]);

// Exact approved DDL, not a registered Customer migration. The isolated
// service owns disposal. No DROP, TRUNCATE, DELETE, migrate:fresh or seeding.
$db->statement(<<<'SQL'
    CREATE TABLE customers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        mobile VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
        is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL,
        UNIQUE KEY uq_customers_mobile (mobile),
        CONSTRAINT chk_customers_status CHECK (is_active IN (0,1)),
        CONSTRAINT chk_customers_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
        CONSTRAINT chk_customers_mobile CHECK (
            mobile IS NULL OR
            (CHAR_LENGTH(mobile) = 11 AND mobile NOT REGEXP '[^0-9]')
        )
    ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    SQL);

$metadata = $db->table('information_schema.COLUMNS')->whereRaw('TABLE_SCHEMA = DATABASE()')->where('TABLE_NAME', 'customers')
    ->orderBy('ORDINAL_POSITION')->get(['COLUMN_NAME as name', 'COLUMN_TYPE as type', 'IS_NULLABLE as nullable', 'COLLATION_NAME as collation_name', 'COLUMN_DEFAULT as default_value', 'EXTRA as extra']);
requireCustomerPreflight($metadata->pluck('name')->all() === ['id', 'name', 'mobile', 'is_active', 'created_at', 'updated_at'], 'Unexpected Customer columns.');
$columns = $metadata->keyBy('name');
requireCustomerPreflight($columns['id']->type === 'bigint unsigned' && $columns['id']->nullable === 'NO' && $columns['id']->extra === 'auto_increment'
    && $columns['name']->type === 'varchar(255)' && $columns['name']->nullable === 'NO' && $columns['name']->collation_name === 'utf8mb4_unicode_ci'
    && $columns['mobile']->type === 'varchar(11)' && $columns['mobile']->nullable === 'YES' && $columns['mobile']->collation_name === 'ascii_bin' && $columns['mobile']->default_value === null
    && $columns['is_active']->type === 'tinyint unsigned' && $columns['is_active']->nullable === 'NO' && (string) $columns['is_active']->default_value === '1'
    && $columns['created_at']->type === 'timestamp' && $columns['created_at']->nullable === 'YES'
    && $columns['updated_at']->type === 'timestamp' && $columns['updated_at']->nullable === 'YES', 'Unexpected Customer column metadata.');
$checks = $db->table('information_schema.TABLE_CONSTRAINTS')->whereRaw('TABLE_SCHEMA = DATABASE()')->where('TABLE_NAME', 'customers')
    ->where('CONSTRAINT_TYPE', 'CHECK')->orderBy('CONSTRAINT_NAME')->pluck('ENFORCED', 'CONSTRAINT_NAME')->all();
requireCustomerPreflight($checks === ['chk_customers_mobile' => 'YES', 'chk_customers_name' => 'YES', 'chk_customers_status' => 'YES'], 'Customer checks not enforced.');
$indexes = $db->table('information_schema.STATISTICS')->whereRaw('TABLE_SCHEMA = DATABASE()')->where('TABLE_NAME', 'customers')
    ->orderBy('INDEX_NAME')->orderBy('SEQ_IN_INDEX')->get(['INDEX_NAME as name', 'COLUMN_NAME as column_name', 'NON_UNIQUE as non_unique']);
requireCustomerPreflight($indexes->map(static fn (object $row): array => [$row->name, $row->column_name, (int) $row->non_unique])->all() === [
    ['PRIMARY', 'id', 0], ['uq_customers_mobile', 'mobile', 0],
], 'Unexpected Customer indexes.');
$table = $db->table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')->where('TABLE_NAME', 'customers')->first(['ENGINE as engine', 'TABLE_COLLATION as collation_name']);
requireCustomerPreflight($table !== null && $table->engine === 'InnoDB' && $table->collation_name === 'utf8mb4_unicode_ci', 'Unexpected Customer engine or table collation.');
emitCustomerPreflight('CUSTOMER_PREFLIGHT_DDL', [
    'showCreate' => (array) $db->selectOne('SHOW CREATE TABLE customers'),
    'columns' => $metadata->all(),
    'checks' => $checks,
    'indexes' => $indexes->all(),
]);

$db->beginTransaction();
$passed = [];
try {
    $a = $db->table('customers')->insertGetId(['name' => 'Synthetic same name', 'mobile' => '00000000001']);
    $b = $db->table('customers')->insertGetId(['name' => 'Synthetic same name', 'mobile' => '00000000002', 'is_active' => 0]);
    $c = $db->table('customers')->insertGetId(['name' => 'Synthetic same name', 'mobile' => null]);
    $d = $db->table('customers')->insertGetId(['name' => 'Synthetic same name']);
    $e = $db->table('customers')->insertGetId(['name' => 'اختبار اصطناعي', 'mobile' => '00000000003']);
    $first = $db->table('customers')->where('id', $a)->first();
    requireCustomerPreflight($first->mobile === '00000000001' && (int) $first->is_active === 1
        && (int) $db->table('customers')->where('id', $b)->value('is_active') === 0, 'Leading zero or status roundtrip failed.');
    requireCustomerPreflight($db->table('customers')->whereNull('mobile')->count() === 2
        && $db->table('customers')->where('name', 'Synthetic same name')->count() === 4, 'Nullable mobile or repeated name failed.');
    requireCustomerPreflight($db->table('customers')->where('id', $e)->value('name') === 'اختبار اصطناعي', 'UTF-8 name roundtrip failed.');
    $passed = ['active_default', 'inactive_value', 'leading_zero', 'multiple_nulls', 'duplicate_names', 'utf8_name'];

    $cases = [
        ['duplicate_active_insert', fn () => $db->table('customers')->insert(['name' => 'Synthetic duplicate', 'mobile' => '00000000001']), 1062, 'uq_customers_mobile'],
        ['duplicate_inactive_insert', fn () => $db->table('customers')->insert(['name' => 'Synthetic duplicate', 'mobile' => '00000000002']), 1062, 'uq_customers_mobile'],
        ['duplicate_update', fn () => $db->table('customers')->where('id', $c)->update(['mobile' => '00000000002']), 1062, 'uq_customers_mobile'],
        ['status_insert_2', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'is_active' => 2]), 3819, 'chk_customers_status'],
        ['status_update_255', fn () => $db->table('customers')->where('id', $a)->update(['is_active' => 255]), 3819, 'chk_customers_status'],
        ['status_null', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'is_active' => null]), 1048, null],
        ['blank_name_insert', fn () => $db->table('customers')->insert(['name' => '   ']), 3819, 'chk_customers_name'],
        ['empty_name_update', fn () => $db->table('customers')->where('id', $a)->update(['name' => '']), 3819, 'chk_customers_name'],
        ['mobile_short', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '0000000001']), 3819, 'chk_customers_mobile'],
        ['mobile_long', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '000000000001']), 1406, null],
        ['mobile_blank', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '']), 3819, 'chk_customers_mobile'],
        ['mobile_letter', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '0000000000a']), 3819, 'chk_customers_mobile'],
        ['mobile_space', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '00000 00000']), 3819, 'chk_customers_mobile'],
        ['mobile_newline', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => "0000000000\n"]), 3819, 'chk_customers_mobile'],
        ['mobile_update_punctuation', fn () => $db->table('customers')->where('id', $a)->update(['mobile' => '0000000000-']), 3819, 'chk_customers_mobile'],
        ['mobile_update_short', fn () => $db->table('customers')->where('id', $a)->update(['mobile' => '123']), 3819, 'chk_customers_mobile'],
    ];
    foreach ($cases as [$label, $operation, $code, $constraint]) {
        customerPreflightRejected($label, $operation, $code, $constraint);
        $passed[] = $label;
    }

    // ASCII must reject Arabic-Indic digits. MySQL may reject the parameter
    // conversion (3854) or the column conversion (1366), both HY000.
    // Keep all other cases' exact codes and named constraints unchanged.
    customerPreflightRejected('non_ascii_mobile', fn () => $db->table('customers')->insert(['name' => 'Synthetic invalid', 'mobile' => '٠٠٠٠٠٠٠٠٠٠١']), [1366, 3854]);
    $passed[] = 'non_ascii_mobile';
    requireCustomerPreflight($db->table('customers')->count() === 5
        && $db->table('customers')->where('id', $a)->value('name') === 'Synthetic same name'
        && $db->table('customers')->where('id', $a)->value('mobile') === '00000000001'
        && (int) $db->table('customers')->where('id', $a)->value('is_active') === 1
        && $db->table('customers')->where('id', $c)->value('mobile') === null, 'Rejected writes altered rows.');
    requireCustomerPreflight($db->table('customers')->where('id', $a)->update(['name' => 'Synthetic renamed', 'mobile' => '00000000001']) === 1, 'Own unchanged mobile update failed.');
    requireCustomerPreflight($db->table('customers')->where('id', $b)->update(['mobile' => null]) === 1
        && $db->table('customers')->whereNull('mobile')->count() === 3, 'Clearing mobile failed.');
    requireCustomerPreflight($db->table('customers')->where('id', $d)->update(['mobile' => '00000000002']) === 1, 'Released mobile could not be reused.');
    $passed = [...$passed, 'failed_write_preservation', 'same_mobile_update', 'clear_mobile', 'released_mobile_reuse'];
} finally {
    $db->rollBack();
}

requireCustomerPreflight($db->table('customers')->count() === 0, 'Fixture rows survived rollback.');
requireCustomerPreflight($snapshot() == $before, 'Existing baseline changed during Customer probe.');
$afterTables = $db->table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')->orderBy('TABLE_NAME')->pluck('TABLE_NAME')->all();
$expectedTables = [...$baselineTables, 'customers'];
sort($expectedTables, SORT_STRING);
requireCustomerPreflight($afterTables === $expectedTables, 'Unexpected additional preflight table.');
emitCustomerPreflight('CUSTOMER_PREFLIGHT_CONSTRAINTS', ['passed' => $passed, 'count' => count($passed)]);
emitCustomerPreflight('CUSTOMER_PREFLIGHT_RESULT', [
    'fixtureRowsAfterRollback' => 0,
    'baselineSchemaAndRowsPreserved' => true,
    'migrationRecordsUnchanged' => 10,
    'scope' => 'isolated synthetic DDL and direct SQL only; no Customer migration, model, API or feature acceptance evidence',
]);
