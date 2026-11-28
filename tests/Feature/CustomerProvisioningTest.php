<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Activities\Support\ActivityPermissions;
use Modules\Identity\Actions\ProvisionCustomerCatalogueAction;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\CustomerCatalogueGrants;
use Modules\Identity\Services\IdentityAccess;
use Modules\Identity\Support\IdentityPermissions;
use Modules\Identity\Support\PermissionCatalogue;
use Modules\People\Support\CustomerPermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AuthenticationCommitFault;

class CustomerProvisioningTest extends IdentityDatabaseTestCase
{
    public function test_fresh_install_exact_defaults_and_both_completion_markers(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $this->assertSame([
            'people.customers.show' => 'عرض بيانات العميل',
            'people.customers.store' => 'إضافة عميل',
            'people.customers.update' => 'تعديل بيانات العميل',
        ], CustomerPermissions::CATALOGUE);
        $this->assertCount(10, IdentityPermissions::keys());
        $this->assertCount(6, IdentityPermissions::ESSENTIAL);
        $this->assertCount(5, ActivityPermissions::keys());
        $this->assertCount(18, PermissionCatalogue::keys());
        $this->assertEquals(PermissionCatalogue::definitions(), Permission::pluck('alias_name', 'name')->all());
        $manager = Role::findByName('manager', 'web');
        $reception = Role::findByName('reception', 'web');
        $this->assertEqualsCanonicalizing(PermissionCatalogue::keys(), $this->keys($manager));
        $this->assertEqualsCanonicalizing([...ActivityPermissions::RECEPTION, ...CustomerPermissions::keys()], $this->keys($reception));
        $this->assertSame(['activities.catalogue.v1', 'people.customers.v1'], DB::table('identity_feature_provisions')->orderBy('feature_key')->pluck('feature_key')->all());
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('model_has_permissions')->count());

        $manager->revokePermissionTo(Permission::findByName('people.customers.store', 'web'));
        $reception->revokePermissionTo(Permission::findByName('people.customers.update', 'web'));
        $before = $this->snapshot();
        app(SeedIdentityCatalogueAction::class)->execute();
        $this->assertFalse(app(ProvisionCustomerCatalogueAction::class)->execute());
        $this->assertSame($before, $this->snapshot());
    }

    public function test_upgrade_definitions_only_then_reviewed_grants_preserve_customization_and_live_authority(): void
    {
        [$manager, $reception, $custom] = $this->legacyCatalogue();
        $staff = User::factory()->create();
        $staff->syncRoles([$reception]);
        $customStaff = User::factory()->create();
        $customStaff->syncRoles([$custom]);
        $managerStaff = User::factory()->create();
        $managerStaff->syncRoles([$manager]);
        $before = $this->snapshot();
        $oldManager = $this->keys($manager);
        $oldReception = $this->keys($reception);
        $oldCustom = $this->keys($custom);
        $this->assertSame(15, Permission::count());
        $this->assertSame(1, Artisan::call('identity:provision-customer-catalogue', ['--confirm-reviewed-upgrade' => true]));
        $this->assertSame($before, $this->snapshot());

        $seed = app(SeedIdentityCatalogueAction::class);
        $seed->execute();
        $seed->execute();
        $this->assertSame(18, Permission::count());
        $this->assertSame($oldManager, $this->keys($manager));
        $this->assertSame($oldReception, $this->keys($reception));
        $this->assertSame($oldCustom, $this->keys($custom));
        $this->assertNull($this->marker());
        $this->assertSame('عرض تجريبي مخصص', Permission::findByName('identity.users.view', 'web')->alias_name);
        $permission = Permission::findByName('people.customers.show', 'web');
        $permission->update(['alias_name' => 'عرض عميل مخصص']);
        $beforeUpgrade = $this->snapshot();
        $this->assertSame(1, Artisan::call('identity:provision-customer-catalogue'));
        $this->assertSame($beforeUpgrade, $this->snapshot());

        $access = app(IdentityAccess::class);
        foreach (CustomerPermissions::keys() as $key) {
            $this->assertFalse($access->allows($staff->id, $key));
            $this->assertFalse($access->allows($managerStaff->id, $key));
            $this->assertFalse($access->allows($customStaff->id, $key));
        }
        $this->assertSame(0, Artisan::call('identity:provision-customer-catalogue', ['--confirm-reviewed-upgrade' => true]));
        $this->assertStringContainsString(__('identity.customer_provision_completed'), Artisan::output());
        $this->assertEqualsCanonicalizing([...$oldManager, ...CustomerPermissions::keys()], $this->keys($manager));
        $this->assertEqualsCanonicalizing([...$oldReception, ...CustomerPermissions::keys()], $this->keys($reception));
        $this->assertSame($oldCustom, $this->keys($custom));
        $this->assertNotContains('identity.audit.view', $this->keys($manager));
        $this->assertNotContains('activities.show', $this->keys($reception));
        $this->assertSame('عرض عميل مخصص', $permission->fresh()->alias_name);
        $afterUpgrade = $this->snapshot();
        foreach (['roles', 'users', 'model_has_roles', 'model_has_permissions', 'permissions'] as $table) {
            $this->assertSame($beforeUpgrade[$table], $afterUpgrade[$table]);
        }
        $this->assertSame($beforeUpgrade['identity_feature_provisions'][0], $afterUpgrade['identity_feature_provisions'][0]);
        $this->assertCount(2, $afterUpgrade['identity_feature_provisions']);
        $this->assertNotNull($this->marker());
        foreach (CustomerPermissions::keys() as $key) {
            $this->assertTrue($access->allows($staff->id, $key));
            $this->assertTrue($access->allows($managerStaff->id, $key));
            $this->assertFalse($access->allows($customStaff->id, $key));
        }

        $reception->fresh()->revokePermissionTo($permission->fresh());
        $beforeRepeat = $this->snapshot();
        $seed->execute();
        $this->assertSame(0, Artisan::call('identity:provision-customer-catalogue', ['--confirm-reviewed-upgrade' => true]));
        $this->assertStringContainsString(__('identity.customer_provision_already_completed'), Artisan::output());
        $this->assertSame($beforeRepeat, $this->snapshot());
        $this->assertFalse($access->allows($staff->id, 'people.customers.show'));
    }

    #[DataProvider('invalidPrerequisites')]
    public function test_invalid_prerequisites_do_not_grant_or_write_marker(string $problem): void
    {
        [$manager, $reception] = $this->legacyCatalogue();
        app(SeedIdentityCatalogueAction::class)->execute();
        match ($problem) {
            'activity-marker' => DB::table('identity_feature_provisions')->where('feature_key', 'activities.catalogue.v1')->delete(),
            'inactive-role' => $reception->update(['is_active' => false]),
            'role-case' => $reception->update(['name' => 'RECEPTION']),
            'missing-definition' => Permission::findByName('people.customers.update', 'web')->delete(),
            'wrong-guard' => Permission::findByName('people.customers.update', 'web')->update(['guard_name' => 'other']),
            'wrong-case' => Permission::findByName('people.customers.update', 'web')->update(['name' => 'PEOPLE.CUSTOMERS.UPDATE']),
            'blank-alias' => Permission::findByName('people.customers.update', 'web')->update(['alias_name' => ' ']),
            'unregistered-grant' => $manager->givePermissionTo(Permission::create(['name' => 'fixture.unregistered', 'guard_name' => 'web', 'alias_name' => 'Unregistered'])),
        };
        $before = $this->snapshot();
        try {
            app(ProvisionCustomerCatalogueAction::class)->execute();
            $this->fail('Invalid prerequisites must refuse.');
        } catch (BusinessException $failure) {
            $this->assertSame('IDENTITY_SEED_REQUIRED', $failure->errorCode);
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertNull($this->marker());
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function invalidPrerequisites(): array
    {
        return array_map(static fn (string $problem): array => [$problem], [
            'activity-marker', 'inactive-role', 'role-case', 'missing-definition', 'wrong-guard', 'wrong-case', 'blank-alias', 'unregistered-grant',
        ]);
    }

    public function test_native_marker_rejection_rolls_back_all_six_upgrade_grants(): void
    {
        $this->legacyCatalogue();
        app(SeedIdentityCatalogueAction::class)->execute();
        $before = $this->snapshot();
        $wait = (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value;
        $this->rejectNextCustomerMarker(6);
        app(PermissionRegistrar::class)->getPermissions();
        try {
            app(ProvisionCustomerCatalogueAction::class)->execute();
            $this->fail('Expected native duplicate marker rejection.');
        } catch (QueryException $failure) {
            $this->assertSame(1062, (int) $failure->errorInfo[1]);
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($wait, (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value);
        $this->assertSame($before, $this->snapshot());
        $this->assertNull($this->marker());
        $this->assertTrue(app(ProvisionCustomerCatalogueAction::class)->execute());
    }

    public function test_fresh_customer_marker_failure_rolls_back_both_markers_and_all_catalogue_rows(): void
    {
        $before = $this->snapshot();
        $this->rejectNextCustomerMarker(6);
        try {
            app(SeedIdentityCatalogueAction::class)->execute();
            $this->fail('Expected native duplicate Customer marker rejection.');
        } catch (QueryException $failure) {
            $this->assertSame(1062, (int) $failure->errorInfo[1]);
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::table('identity_feature_provisions')->count());
        app(SeedIdentityCatalogueAction::class)->execute();
        $this->assertSame(2, DB::table('identity_feature_provisions')->count());
    }

    public function test_lost_commit_acknowledgement_is_uncertain_and_never_replays_first_grant(): void
    {
        [$manager, $reception] = $this->legacyCatalogue();
        app(SeedIdentityCatalogueAction::class)->execute();
        $connection = DB::connection();
        $oldId = (int) $connection->selectOne('SELECT CONNECTION_ID() AS id')->id;
        $fault = new AuthenticationCommitFault($connection->getPdo(), $connection);
        $connection->setPdo($fault);
        try {
            app(ProvisionCustomerCatalogueAction::class)->execute();
            $this->fail('Lost acknowledgement must be reported as uncertain.');
        } catch (BusinessException $failure) {
            $this->assertSame(503, $failure->httpStatus);
            $this->assertSame('IDENTITY_CUSTOMER_PROVISION_UNCERTAIN', $failure->errorCode);
        }
        $this->assertSame(1, $fault->commits);
        $this->assertNull($connection->getRawPdo());
        $this->assertNotSame($oldId, (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
        $this->assertNotNull($this->marker());
        foreach (CustomerPermissions::keys() as $key) {
            $this->assertContains($key, $this->keys($manager));
            $this->assertContains($key, $this->keys($reception));
        }
        $reception->fresh()->revokePermissionTo(Permission::findByName('people.customers.update', 'web'));
        $before = $this->snapshot();
        $this->assertFalse(app(ProvisionCustomerCatalogueAction::class)->execute());
        $this->assertSame($before, $this->snapshot());
    }

    private function rejectNextCustomerMarker(int $expectedGrants): void
    {
        $connection = DB::connection();
        $armed = true;
        $connection->beforeExecuting(function (string $query, array $bindings) use ($connection, $expectedGrants, &$armed): void {
            $sql = strtolower(str_replace(chr(96), '', $query));
            if (! $armed || ! str_starts_with($sql, 'insert into identity_feature_provisions')
                || ! in_array(CustomerCatalogueGrants::FEATURE_KEY, $bindings, true)) {
                return;
            }
            $armed = false;
            $pdo = $connection->getPdo();
            $this->assertTrue($pdo->inTransaction());
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM role_has_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.name LIKE ?');
            $stmt->execute(['people.customers.%']);
            $this->assertSame($expectedGrants, (int) $stmt->fetchColumn());
            $stmt = $pdo->prepare('INSERT INTO identity_feature_provisions(feature_key, completed_at) VALUES (?, ?)');
            $stmt->execute([CustomerCatalogueGrants::FEATURE_KEY, '2026-11-28 08:00:00']);
        });
    }

    private function legacyCatalogue(): array
    {
        $manager = Role::create(['name' => 'manager', 'guard_name' => 'web', 'is_active' => true]);
        $reception = Role::create(['name' => 'reception', 'guard_name' => 'web', 'is_active' => true]);
        $custom = Role::create(['name' => 'Synthetic Custom', 'guard_name' => 'web', 'is_active' => true]);
        foreach (IdentityPermissions::CATALOGUE + ActivityPermissions::CATALOGUE as $name => $alias) {
            $permission = Permission::create([
                'name' => $name, 'guard_name' => 'web',
                'alias_name' => $name === 'identity.users.view' ? 'عرض تجريبي مخصص' : $alias,
            ]);
            if ($name !== 'identity.audit.view') {
                $manager->givePermissionTo($permission);
            }
            if (in_array($name, ['activities.index', 'identity.users.view'], true)) {
                $reception->givePermissionTo($permission);
            }
            if ($name === 'activities.show') {
                $custom->givePermissionTo($permission);
            }
        }
        DB::table('identity_feature_provisions')->insert(['feature_key' => 'activities.catalogue.v1', 'completed_at' => '2026-11-28 08:00:00']);

        return [$manager, $reception, $custom];
    }

    private function keys(Role $role): array
    {
        return $role->permissions()->orderBy('permissions.name')->pluck('permissions.name')->all();
    }

    private function marker(): ?array
    {
        $row = DB::table('identity_feature_provisions')->where('feature_key', CustomerCatalogueGrants::FEATURE_KEY)->first();

        return $row === null ? null : (array) $row;
    }

    private function snapshot(): array
    {
        $orders = [
            'roles' => ['id'], 'permissions' => ['id'], 'users' => ['id'],
            'role_has_permissions' => ['role_id', 'permission_id'], 'model_has_roles' => ['role_id', 'model_type', 'model_id'],
            'model_has_permissions' => ['permission_id', 'model_type', 'model_id'], 'identity_feature_provisions' => ['feature_key'],
        ];
        $state = [];
        foreach ($orders as $table => $columns) {
            $query = DB::table($table);
            foreach ($columns as $column) {
                $query->orderBy($column);
            }
            $state[$table] = $query->get()->map(static fn (object $row): array => (array) $row)->all();
        }

        return $state;
    }
}
