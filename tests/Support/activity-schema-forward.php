<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $failure): void {
    fwrite(STDERR, 'Activity forward verification aborted: '.$failure::class.' at '.basename($failure->getFile()).':'.$failure->getLine().PHP_EOL);
    exit(1);
});

function requireActivityForward(bool $condition, string $reason): void
{
    if (! $condition) {
        fwrite(STDERR, $reason.PHP_EOL);
        throw new RuntimeException($reason);
    }
}

$db = DB::connection();
requireActivityForward(getenv('GITHUB_ACTIONS') === 'true' && $app->environment('testing')
    && getenv('DB_URL') === '' && $db->getDriverName() === 'mysql'
    && $db->getConfig('host') === '127.0.0.1' && (string) $db->getConfig('port') === '3306'
    && ! $db->getConfig('unix_socket') && $db->getDatabaseName() === 'kids_area_test'
    && $db->getConfig('username') === 'kids_area_ci', 'Unsafe Activity forward verification environment.');
$server = $db->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS principal, VERSION() AS version, @@default_storage_engine AS engine');
requireActivityForward($server->db === 'kids_area_test' && str_starts_with($server->principal, 'kids_area_ci@')
    && str_starts_with($server->version, '8.') && $server->engine === 'InnoDB', 'Unsafe Activity forward verification server.');

$orders = [
    'identity_login_throttles' => ['scope', 'key_digest'],
    'migrations' => ['id'],
    'model_has_permissions' => ['permission_id', 'model_type', 'model_id'],
    'model_has_roles' => ['role_id', 'model_type', 'model_id'],
    'permissions' => ['id'],
    'role_has_permissions' => ['role_id', 'permission_id'],
    'roles' => ['id'],
    'sessions' => ['id'],
    'users' => ['id'],
];
$actualTables = $db->table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')->orderBy('TABLE_NAME')->pluck('TABLE_NAME')->all();
requireActivityForward($actualTables === array_keys($orders), 'Expected the exact pre-Catalogue schema.');
requireActivityForward($db->table('migrations')->count() === 8
    && $db->table('migrations')->where('migration', '2026_11_26_000008_remove_alias_name_from_roles')->exists()
    && $db->table('roles')->count() === 4 && $db->table('users')->count() === 4
    && $db->table('permissions')->count() === 10
    && $db->table('sessions')->whereNotNull('auth_user_id')->count() === 2
    && $db->table('identity_login_throttles')->count() === 3
    && $db->table('permissions')->where('name', 'identity.users.view')->value('alias_name') === 'عرض تجريبي مخصص',
    'Expected guarded, populated historical Roles fixture.');

$snapshot = static function () use ($db, $orders): array {
    $rows = [];
    $schema = [];
    foreach ($orders as $table => $columns) {
        $query = $db->table($table);
        foreach ($columns as $column) {
            $query->orderBy($column);
        }
        $rows[$table] = $query->get()->all();
        $schema[$table] = (array) $db->selectOne('SHOW CREATE TABLE '.chr(96).$table.chr(96));
    }

    return ['rows' => $rows, 'schema' => $schema];
};

$before = $snapshot();
$runnerTemp = getenv('RUNNER_TEMP');
requireActivityForward(is_string($runnerTemp) && is_dir($runnerTemp) && is_writable($runnerTemp), 'Missing isolated runner directory.');
$backup = json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$backupPath = $runnerTemp.DIRECTORY_SEPARATOR.'activity-baseline-synthetic.json';
$handle = fopen($backupPath, 'xb');
requireActivityForward(is_resource($handle), 'Unable to create exclusive synthetic backup.');
try {
    requireActivityForward(chmod($backupPath, 0600), 'Unable to restrict synthetic backup.');
    requireActivityForward(fwrite($handle, $backup) === strlen($backup) && fflush($handle), 'Unable to write synthetic backup.');
} finally {
    fclose($handle);
}
requireActivityForward(file_get_contents($backupPath) === $backup, 'Synthetic backup did not round-trip.');
// Do not print synthetic session payloads, password hashes or backup contents.

$paths = [
    'Modules/Identity/database/migrations/2026_11_26_000009_create_identity_feature_provisions_table.php',
    'Modules/Activities/database/migrations/2026_11_26_000010_create_activities_table.php',
];
requireActivityForward(Artisan::call('migrate', ['--force' => true, '--path' => $paths]) === 0, 'Approved additive Activity migrations failed.');
$after = $snapshot();
$oldMigrations = $before['rows']['migrations'];
$newMigrations = $after['rows']['migrations'];
requireActivityForward(count($newMigrations) === count($oldMigrations) + 2
    && array_slice($newMigrations, 0, count($oldMigrations)) == $oldMigrations
    && array_map(static fn ($row) => $row->migration, array_slice($newMigrations, -2)) === [
        '2026_11_26_000009_create_identity_feature_provisions_table',
        '2026_11_26_000010_create_activities_table',
    ], 'Unexpected migration history change.');
unset($before['rows']['migrations'], $after['rows']['migrations'], $before['schema']['migrations'], $after['schema']['migrations']);
requireActivityForward($before == $after, 'An additive migration modified existing rows or schema.');
// Compare migration table definition excluding only its expected sequence advance.
$oldMigrationSchema = json_decode($backup, true, 512, JSON_THROW_ON_ERROR)['schema']['migrations'];
$newMigrationSchema = (array) $db->selectOne('SHOW CREATE TABLE migrations');
$normalizeSequence = static fn (array $definition): array => array_map(static fn (string $value): string => preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $value), $definition);
requireActivityForward($normalizeSequence($oldMigrationSchema) === $normalizeSequence($newMigrationSchema), 'Migration table definition changed.');
$expectedTables = [...array_keys($orders), 'activities', 'identity_feature_provisions'];
sort($expectedTables, SORT_STRING);
requireActivityForward($db->table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')->orderBy('TABLE_NAME')->pluck('TABLE_NAME')->all() === $expectedTables
    && $db->table('activities')->count() === 0 && $db->table('identity_feature_provisions')->count() === 0,
    'Unexpected new table scope or seeded data.');

echo 'ACTIVITY_FORWARD_MIGRATIONS '.json_encode([
    'mysql' => $server->version,
    'baselineTablesPreserved' => array_keys($after['rows']),
    'baselineCounts' => array_map('count', $after['rows']),
    'oldMigrationRecordsPreserved' => count($oldMigrations),
    'newMigrationRecords' => 2,
    'newTables' => ['activities', 'identity_feature_provisions'],
    'newTablesEmpty' => true,
    'backupRoundTrip' => true,
    'oldSchemaPreserved' => true,
    'oldRowsPreserved' => true,
], JSON_THROW_ON_ERROR).PHP_EOL;
