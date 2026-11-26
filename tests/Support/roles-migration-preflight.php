<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\IdentityPermissions;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationHttpServer;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Laravel's console exception renderer alone need not set the process exit status.
// Never let an incomplete standalone verification appear as a passing CI step.
set_exception_handler(static function (Throwable $failure): void {
    fwrite(STDERR, 'Roles migration verification aborted: '.$failure::class.' at '.basename($failure->getFile()).':'.$failure->getLine().PHP_EOL);
    exit(1);
});

function requireRolesPreflight(bool $condition, string $reason): void
{
    if (! $condition) {
        fwrite(STDERR, $reason.PHP_EOL);
        throw new RuntimeException($reason);
    }
}

$db = DB::connection();
requireRolesPreflight(getenv('GITHUB_ACTIONS') === 'true'
    && $app->environment('testing') && $db->getDriverName() === 'mysql'
    && $db->getConfig('host') === '127.0.0.1' && $db->getDatabaseName() === 'kids_area_test'
    && $db->getConfig('username') === 'kids_area_ci' && getenv('DB_URL') === ''
    && config('permission.teams') === false, 'Unsafe Roles preflight environment.');
$server = $db->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS principal, VERSION() AS version, @@default_storage_engine AS engine');
requireRolesPreflight($server->db === 'kids_area_test' && str_starts_with($server->principal, 'kids_area_ci@')
    && str_starts_with($server->version, '8.') && $server->engine === 'InnoDB', 'Unsafe Roles preflight server.');
$runnerTemp = getenv('RUNNER_TEMP');
requireRolesPreflight(is_string($runnerTemp) && is_dir($runnerTemp) && is_writable($runnerTemp), 'Missing isolated runner temporary directory.');

$tables = ['roles', 'permissions', 'model_has_roles', 'role_has_permissions', 'model_has_permissions', 'users', 'sessions', 'identity_login_throttles'];
$columns = DB::table('information_schema.COLUMNS')->whereRaw('TABLE_SCHEMA = DATABASE()')
    ->whereIn('TABLE_NAME', $tables)->orderBy('TABLE_NAME')->orderBy('ORDINAL_POSITION')
    ->get(['TABLE_NAME as table_name', 'COLUMN_NAME as column_name', 'COLUMN_TYPE as column_type',
        'IS_NULLABLE as is_nullable', 'COLLATION_NAME as collation_name', 'COLUMN_DEFAULT as default_value']);
$roleColumns = $columns->where('table_name', 'roles')->keyBy('column_name');
requireRolesPreflight(array_keys($roleColumns->all()) === ['id', 'name', 'guard_name', 'alias_name', 'is_active', 'created_at', 'updated_at']
    && $roleColumns['alias_name']->column_type === 'varchar(255)'
    && $roleColumns['alias_name']->is_nullable === 'NO', 'Unexpected legacy Role schema.');
requireRolesPreflight($columns->where('table_name', 'permissions')->where('column_name', 'alias_name')->count() === 1,
    'Missing permission display alias.');

$indexes = [];
foreach ($tables as $table) {
    $indexes[$table] = collect(DB::select('SHOW INDEX FROM '.$table))
        ->map(fn ($row) => array_change_key_case((array) $row))
        ->sortBy([['key_name', 'asc'], ['seq_in_index', 'asc']])->values()
        ->map(fn (array $row) => [
            'name' => $row['key_name'],
            'column' => $row['column_name'],
            'sequence' => (int) $row['seq_in_index'],
            'nonUnique' => (int) $row['non_unique'],
            'prefix' => $row['sub_part'],
        ])->all();
}
$unique = collect($indexes['roles'])->where('name', 'roles_name_guard_name_unique')->values();
requireRolesPreflight($unique->pluck('column')->all() === ['name', 'guard_name']
    && $unique->every(fn (array $row) => $row['nonUnique'] === 0 && $row['prefix'] === null), 'Missing exact Role unique constraint.');
$single = collect($indexes['model_has_roles'])->where('name', 'identity_single_role_unique')->values();
requireRolesPreflight($single->pluck('column')->all() === ['model_type', 'model_id']
    && $single->every(fn (array $row) => $row['nonUnique'] === 0), 'Missing exact single-role constraint.');
foreach (['model_has_roles', 'role_has_permissions'] as $table) {
    requireRolesPreflight(collect($indexes[$table])->contains(fn (array $row) => $row['column'] === 'role_id' && $row['sequence'] === 1),
        'Missing leading role membership/grant index.');
}
requireRolesPreflight(collect($indexes['sessions'])->where('name', 'sessions_auth_owner_index')->pluck('column')->all() === ['auth_user_id', 'id'],
    'Missing session owner index.');
$foreignKeys = DB::select(
    'SELECT k.TABLE_NAME AS table_name, k.COLUMN_NAME AS column_name, k.CONSTRAINT_NAME AS constraint_name, '
    .'k.REFERENCED_TABLE_NAME AS referenced_table, k.REFERENCED_COLUMN_NAME AS referenced_column, r.DELETE_RULE AS delete_rule '
    .'FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
    .'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME '
    .'WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL '
    .'ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION'
);
foreach ([
    ['model_has_roles', 'role_id', 'roles'],
    ['role_has_permissions', 'role_id', 'roles'],
    ['role_has_permissions', 'permission_id', 'permissions'],
] as [$table, $column, $target]) {
    requireRolesPreflight(collect($foreignKeys)->contains(fn ($row) => $row->table_name === $table
        && $row->column_name === $column && $row->referenced_table === $target
        && $row->referenced_column === 'id' && $row->delete_rule === 'CASCADE'), 'Missing reviewed Role foreign key.');
}
$engines = DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')->whereIn('TABLE_NAME', $tables)
    ->pluck('ENGINE', 'TABLE_NAME');
requireRolesPreflight($engines->count() === count($tables) && $engines->every(fn ($engine) => $engine === 'InnoDB'),
    'Unexpected storage engine in the Roles fixture.');
echo 'ROLES_SCHEMA_METADATA '.json_encode([
    'mysql' => $server->version,
    'columns' => $columns->whereIn('table_name', ['roles', 'permissions'])->values()->all(),
    'indexes' => $indexes,
    'foreignKeys' => $foreignKeys,
    'engines' => $engines->all(),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;

// This is the empty, freshly migrated GitHub service, not a reset or a real database.
foreach ($tables as $table) {
    requireRolesPreflight(DB::table($table)->doesntExist(), 'Roles preflight requires an empty disposable fixture.');
}
config()->set('hashing.argon.memory', 8192);
config()->set('hashing.argon.time', 2);
$now = now();
$roles = [];
foreach ([
    ['manager', 'مدير', true],
    ['reception', 'استقبال', true],
    ['مشرف الأنشطة', 'اسم قديم مختلف', true],
    ['Legacy.Disabled', 'دور قديم غير نشط', false],
] as [$name, $alias, $active]) {
    $roles[$name] = DB::table('roles')->insertGetId([
        'name' => $name, 'guard_name' => 'web', 'alias_name' => $alias,
        'is_active' => $active, 'created_at' => $now, 'updated_at' => $now,
    ]);
}
$permissions = [];
foreach (IdentityPermissions::CATALOGUE as $name => $alias) {
    $permissions[$name] = DB::table('permissions')->insertGetId([
        'name' => $name, 'guard_name' => 'web', 'alias_name' => $alias,
        'created_at' => $now, 'updated_at' => $now,
    ]);
}
Role::findById($roles['manager'], 'web')->syncPermissions(array_keys($permissions));
Role::findById($roles['مشرف الأنشطة'], 'web')->syncPermissions(['identity.users.view']);
// A changed stored permission alias must survive unchanged, too.
DB::table('permissions')->where('id', $permissions['identity.users.view'])->update(['alias_name' => 'عرض تجريبي مخصص']);

$users = [];
foreach ([
    ['Fixture.RoleFounder', 'manager', true, 1],
    ['Fixture.RoleStaff', 'مشرف الأنشطة', true, 3],
    ['Fixture.RoleInactive', 'مشرف الأنشطة', false, 5],
    ['Fixture.RoleDisabled', 'Legacy.Disabled', true, 7],
] as [$username, $role, $active, $generation]) {
    $id = DB::table('users')->insertGetId([
        'name' => 'Synthetic Role fixture', 'username' => $username,
        'email' => null, 'phone' => null, 'password' => Hash::make('test-fixture-only-password'),
        'is_active' => $active, 'auth_version' => $generation, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $user = User::query()->findOrFail($id);
    $user->syncRoles([Role::findById($roles[$role], 'web')]);
    $users[$username] = $id;
}
requireRolesPreflight($users['Fixture.RoleFounder'] === 1, 'Unexpected synthetic founder ID.');

$equivalence = [];
foreach (['manager', 'MANAGER', 'mánaGer', 'manager '] as $candidate) {
    $equivalence[$candidate] = (int) DB::table('roles')->where('name', $candidate)->where('guard_name', 'web')->value('id') === (int) $roles['manager'];
}
requireRolesPreflight($equivalence['manager'], 'Permanent name lookup failed.');
echo 'ROLES_NAME_EQUIVALENCE '.json_encode([
    'collation' => $roleColumns['name']->collation_name,
    'matchesManager' => $equivalence,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;

// Use actual native browser/database sessions, not fabricated authority rows.
// Match the existing guarded test router's exact temporary-directory contract.
$barrierDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kids-auth-'.bin2hex(random_bytes(10));
requireRolesPreflight(mkdir($barrierDir, 0700), 'Unable to create isolated HTTP fixture directory.');
$http = new AuthenticationHttpServer($barrierDir);
try {
    $browsers = [];
    $meBefore = [];
    foreach (['Fixture.RoleFounder', 'Fixture.RoleStaff'] as $username) {
        $browser = new AuthenticationBrowser($http);
        $handshake = $browser->handshake();
        requireRolesPreflight($handshake->status === 204, 'Synthetic role fixture handshake failed: HTTP '.$handshake->status.'. '.$http->diagnostic());
        $login = $browser->login($username);
        requireRolesPreflight($login->status === 200, 'Synthetic role fixture login failed: HTTP '.$login->status.'. '.$http->diagnostic());
        $response = $browser->me();
        requireRolesPreflight($response->status === 200, 'Synthetic role fixture Me failed.');
        $browsers[$username] = $browser;
        $meBefore[$username] = $response->json()['data'];
        $roleName = $username === 'Fixture.RoleFounder' ? 'manager' : 'مشرف الأنشطة';
        requireRolesPreflight($meBefore[$username]['role'] === [
            'id' => (int) $roles[$roleName], 'name' => $roleName,
        ], 'Unexpected single-name role projection.');
    }
    requireRolesPreflight(DB::table('sessions')->whereNotNull('auth_user_id')->count() === 2, 'Expected two native authoritative fixture sessions.');

    $legacyAliases = DB::table('roles')->orderBy('id')->get(['id', 'name', 'alias_name'])->all();
    $roleProjection = array_values(array_filter(Schema::getColumnListing('roles'), fn (string $column) => $column !== 'alias_name'));
    $orders = [
        'roles' => ['id'], 'permissions' => ['id'], 'users' => ['id'], 'sessions' => ['id'],
        'model_has_roles' => ['role_id', 'model_type', 'model_id'],
        'model_has_permissions' => ['permission_id', 'model_type', 'model_id'],
        'role_has_permissions' => ['role_id', 'permission_id'],
            ];
    // Derive the throttle table ordering from its actual primary index; no assumption about column names.
    $orders['identity_login_throttles'] = collect($indexes['identity_login_throttles'])
        ->where('name', 'PRIMARY')->pluck('column')->all();
    requireRolesPreflight($orders['identity_login_throttles'] !== [], 'Missing throttle primary key.');
    $snapshot = static function () use ($orders, $roleProjection): array {
        $fingerprints = [];
        $counts = [];
        foreach ($orders as $table => $order) {
            $query = DB::table($table);
            foreach ($order as $column) {
                $query->orderBy($column);
            }
            $rows = $query->get($table === 'roles' ? $roleProjection : ['*'])->all();
            $counts[$table] = count($rows);
            $fingerprints[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }

        return ['counts' => $counts, 'fingerprints' => $fingerprints];
    };
    $before = $snapshot();
    $backup = json_encode([
        'legacyRoleAliases' => $legacyAliases,
        'roleProjection' => $roleProjection,
        'counts' => $before['counts'],
        'fingerprints' => $before['fingerprints'],
        'metadata' => ['columns' => $columns->all(), 'indexes' => $indexes, 'foreignKeys' => $foreignKeys],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $backupPath = $runnerTemp.DIRECTORY_SEPARATOR.'roles-legacy-fixture.json';
    $handle = fopen($backupPath, 'xb');
    requireRolesPreflight(is_resource($handle), 'Unable to create exclusive synthetic backup.');
    try {
        requireRolesPreflight(chmod($backupPath, 0600), 'Unable to restrict synthetic backup.');
        requireRolesPreflight(fwrite($handle, $backup) === strlen($backup) && fflush($handle), 'Unable to persist synthetic backup.');
    } finally {
        fclose($handle);
    }
    requireRolesPreflight(file_get_contents($backupPath) === $backup, 'Synthetic backup did not round-trip.');
    $saved = json_decode((string) file_get_contents($backupPath), true, 512, JSON_THROW_ON_ERROR);
    requireRolesPreflight(count($saved['legacyRoleAliases']) === 4 && $saved['fingerprints'] === $before['fingerprints'],
        'Synthetic backup could not be read back.');
    echo 'ROLES_LEGACY_FIXTURE '.json_encode([
        'counts' => $before['counts'], 'aliasesExported' => count($legacyAliases),
        'nativeAuthoritativeSessions' => 2, 'backupRoundTrip' => true,
        'roleAliasStillPresent' => Schema::hasColumn('roles', 'alias_name'),
    ], JSON_THROW_ON_ERROR).PHP_EOL;

    $expectedMigration = '2026_11_26_000008_remove_alias_name_from_roles';
    requireRolesPreflight(! DB::table('migrations')->where('migration', $expectedMigration)->exists()
        && Schema::hasColumn('roles', 'alias_name'), 'Expected the pre-drop legacy state.');
    $appliedBefore = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
    requireRolesPreflight(count($appliedBefore) === 7, 'Unexpected historical migration inventory.');
    // The separately authorized forward DDL; no new reset or down procedure.
    requireRolesPreflight(Artisan::call('migrate', ['--force' => true,
        '--path' => ['Modules/Identity/database/migrations/'.$expectedMigration.'.php']]) === 0,
        'Forward Role alias migration failed.');
    requireRolesPreflight(DB::table('migrations')->orderBy('id')->pluck('migration')->all() === [...$appliedBefore, $expectedMigration],
        'Unexpected forward migration inventory.');
    requireRolesPreflight(Schema::getColumnListing('roles') === $roleProjection
        && ! Schema::hasColumn('roles', 'alias_name') && Schema::hasColumn('permissions', 'alias_name'),
        'Unexpected target role/permission schema.');
    $after = $snapshot();
    requireRolesPreflight($after === $before, 'Forward migration changed non-alias data.');
    $afterColumns = DB::table('information_schema.COLUMNS')->whereRaw('TABLE_SCHEMA = DATABASE()')
        ->whereIn('TABLE_NAME', $tables)->orderBy('TABLE_NAME')->orderBy('ORDINAL_POSITION')
        ->get(['TABLE_NAME as table_name', 'COLUMN_NAME as column_name', 'COLUMN_TYPE as column_type',
            'IS_NULLABLE as is_nullable', 'COLLATION_NAME as collation_name', 'COLUMN_DEFAULT as default_value']);
    requireRolesPreflight($afterColumns->all() == $columns->reject(fn ($column) => $column->table_name === 'roles'
        && $column->column_name === 'alias_name')->values()->all(), 'Unexpected column definition change.');
    foreach ($tables as $table) {
        $afterIndexes = collect(DB::select('SHOW INDEX FROM '.$table))
            ->map(fn ($row) => array_change_key_case((array) $row))
            ->sortBy([['key_name', 'asc'], ['seq_in_index', 'asc']])->values()
            ->map(fn (array $row) => [
                'name' => $row['key_name'], 'column' => $row['column_name'],
                'sequence' => (int) $row['seq_in_index'], 'nonUnique' => (int) $row['non_unique'],
                'prefix' => $row['sub_part'],
            ])->all();
        requireRolesPreflight($afterIndexes === $indexes[$table], 'Unexpected index definition change.');
    }
    $afterForeignKeys = DB::select(
        'SELECT k.TABLE_NAME AS table_name, k.COLUMN_NAME AS column_name, k.CONSTRAINT_NAME AS constraint_name, '
        .'k.REFERENCED_TABLE_NAME AS referenced_table, k.REFERENCED_COLUMN_NAME AS referenced_column, r.DELETE_RULE AS delete_rule '
        .'FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
        .'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME '
        .'WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL '
        .'ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION'
    );
    requireRolesPreflight($afterForeignKeys == $foreignKeys, 'Unexpected foreign key definition change.');

    // The raw fingerprints were compared before new HTTP requests can touch sessions.
    foreach ($browsers as $username => $browser) {
        $response = $browser->me();
        requireRolesPreflight($response->status === 200 && $response->json()['data'] === $meBefore[$username],
            'Existing authoritative browser session or role contract changed.');
    }
    $response = $browsers['Fixture.RoleFounder']->request('GET', '/api/v1/selects?selects=roles,userFilterRoles');
    requireRolesPreflight($response->status === 200, 'Role Selects failed after migration.');
    $selects = $response->json()['data'];
    $expected = static fn (bool $active): array => DB::table('roles')->whereRaw('BINARY guard_name = ?', ['web'])
        ->when($active, fn ($query) => $query->where('is_active', true))->orderBy('id')->get(['id', 'name'])
        ->map(fn ($row) => ['value' => (int) $row->id, 'label' => $row->name])->all();
    requireRolesPreflight($selects === [
        ['label' => 'roles', 'options' => $expected(true)],
        ['label' => 'userFilterRoles', 'options' => $expected(false)],
    ], 'Role Select names or eligibility changed.');
    requireRolesPreflight(DB::table('permissions')->where('name', 'identity.users.view')->value('alias_name') === 'عرض تجريبي مخصص',
        'Permission alias was not preserved.');
    echo 'ROLES_FORWARD_MIGRATION '.json_encode([
        'migration' => $expectedMigration, 'preservedTables' => array_keys($after['fingerprints']),
        'counts' => $after['counts'], 'roleAliasRemoved' => true, 'permissionAliasPreserved' => true,
        'schemaIndexesForeignKeysPreserved' => true, 'existingBrowserSessionsVerified' => count($browsers),
        'roleSelectNamesVerified' => true, 'backupRoundTrip' => true,
    ], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $http->stop();
    if (is_dir($barrierDir) && ! is_link($barrierDir)) {
        rmdir($barrierDir);
    }
}
// The existing approved isolated regression harness resets its own fixtures.
// No newly introduced destructive reset or schema rollback.
