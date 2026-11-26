<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Activities\Support\ActivityPermissions;
use Modules\Identity\Actions\ProvisionActivityCatalogueAction;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\ActivityCatalogueGrants;
use Modules\Identity\Services\IdentityAccess;
use Modules\Identity\Support\IdentityPermissions;
use Modules\Identity\Support\PermissionCatalogue;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\ActivityProvisionFixtures;
use Tests\Support\AuthenticationCommitFault;

class ActivityProvisioningTest extends IdentityDatabaseTestCase
{
    use ActivityProvisionFixtures;

    public function test_legacy_seed_is_additive_then_reviewed_command_grants_once_without_overwriting(): void
    {
        [$manager, $reception] = $this->legacyActivityRoles(false);
        $permission = Permission::findByName('identity.users.view', 'web');
        $permission->update(['alias_name' => 'عرض مخصص']);
        $reception->givePermissionTo($permission);
        $custom = Role::create(['name' => 'Custom disabled', 'guard_name' => 'web', 'is_active' => false]);
        $custom->givePermissionTo($permission);
        $staff = User::factory()->create(['is_active' => false]);
        $staff->syncRoles([$custom]);
        $oldManager = $this->grantKeys($manager);
        $oldReception = $this->grantKeys($reception);
        $oldCustom = $this->grantKeys($custom);
        $oldRole = $custom->fresh()->getRawOriginal();
        $oldStaff = $staff->fresh()->getRawOriginal();
        $seed = app(SeedIdentityCatalogueAction::class);
        $seed->execute();
        $seed->execute();
        $this->assertCount(10, IdentityPermissions::keys());
        $this->assertCount(6, IdentityPermissions::ESSENTIAL);
        $this->assertCount(15, PermissionCatalogue::keys());
        $this->assertEquals(PermissionCatalogue::definitions()['activities.index'], Permission::findByName('activities.index', 'web')->alias_name);
        $this->assertEqualsCanonicalizing(PermissionCatalogue::keys(), Permission::pluck('name')->all());
        $this->assertSame($oldManager, $this->grantKeys($manager));
        $this->assertSame($oldReception, $this->grantKeys($reception));
        $this->assertSame(0, DB::table('identity_feature_provisions')->count());

        $activityPermission = Permission::findByName('activities.show', 'web');
        $activityPermission->update(['alias_name' => 'نشاط مخصص']);
        $this->assertSame(1, Artisan::call('identity:provision-activity-catalogue'));
        $this->assertNull($this->marker());
        $this->assertSame(0, Artisan::call('identity:provision-activity-catalogue', ['--confirm-reviewed-upgrade' => true]), Artisan::output());
        $this->assertStringContainsString(__('identity.activity_provision_completed'), Artisan::output());
        $this->assertEqualsCanonicalizing([...$oldManager, ...ActivityPermissions::keys()], $this->grantKeys($manager));
        $this->assertEqualsCanonicalizing([...$oldReception, ...ActivityPermissions::RECEPTION], $this->grantKeys($reception));
        $this->assertSame($oldCustom, $this->grantKeys($custom));
        $this->assertSame($oldRole, $custom->fresh()->getRawOriginal());
        $this->assertSame($oldStaff, $staff->fresh()->getRawOriginal());
        $this->assertSame('عرض مخصص', $permission->fresh()->alias_name);
        $this->assertSame('نشاط مخصص', $activityPermission->fresh()->alias_name);
        $marker = $this->marker();
        $this->assertNotNull($marker);
        $this->assertSame(ActivityCatalogueGrants::FEATURE_KEY, $marker['feature_key']);
        $this->assertNotEmpty($marker['completed_at']);

        $manager->revokePermissionTo(Permission::findByName('activities.delete', 'web'));
        $reception->revokePermissionTo($activityPermission);
        $after = $this->snapshot();
        $seed->execute();
        $this->assertSame(0, Artisan::call('identity:provision-activity-catalogue', ['--confirm-reviewed-upgrade' => true]));
        $this->assertStringContainsString(__('identity.activity_provision_already_completed'), Artisan::output());
        $this->assertSame($after, $this->snapshot());
        $this->assertSame($marker, $this->marker());
        $this->assertNotContains('identity.audit.view', $this->grantKeys($manager));
        $this->assertNotContains('activities.delete', $this->grantKeys($manager));
        $this->assertNotContains('activities.show', $this->grantKeys($reception));
    }

    public function test_fresh_seed_marker_blocks_later_default_grant_restoration(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $manager = Role::findByName('manager', 'web');
        $reception = Role::findByName('reception', 'web');
        $this->assertEqualsCanonicalizing(PermissionCatalogue::keys(), $this->grantKeys($manager));
        $this->assertEqualsCanonicalizing(ActivityPermissions::RECEPTION, $this->grantKeys($reception));
        $manager->revokePermissionTo(Permission::findByName('activities.store', 'web'));
        $reception->revokePermissionTo(Permission::findByName('activities.index', 'web'));
        $before = $this->snapshot();
        $this->assertFalse(app(ProvisionActivityCatalogueAction::class)->execute());
        app(SeedIdentityCatalogueAction::class)->execute();
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('invalidPrerequisites')]
    public function test_invalid_upgrade_prerequisites_refuse_without_changes(string $problem): void
    {
        [$manager, $reception] = $this->legacyActivityRoles($problem !== 'not-seeded');
        match ($problem) {
            'not-seeded' => null,
            'inactive-role' => $reception->update(['is_active' => false]),
            'role-case' => $reception->update(['name' => 'RECEPTION']),
            'permission-case' => Permission::findByName('activities.show', 'web')->update(['name' => 'ACTIVITIES.SHOW']),
            'permission-guard' => Permission::findByName('activities.show', 'web')->update(['guard_name' => 'other']),
            'blank-alias' => Permission::findByName('activities.show', 'web')->update(['alias_name' => ' ']),
            'unregistered-grant' => $manager->givePermissionTo(Permission::create([
                'name' => 'fixture.unregistered', 'guard_name' => 'web', 'alias_name' => 'Unregistered',
            ])),
        };
        $before = $this->snapshot();
        try {
            app(ProvisionActivityCatalogueAction::class)->execute();
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
        return array_map(static fn ($problem) => [$problem], [
            'not-seeded', 'inactive-role', 'role-case', 'permission-case', 'permission-guard', 'blank-alias', 'unregistered-grant',
        ]);
    }

    public function test_actual_mysql_marker_rejection_rolls_back_all_seven_grants(): void
    {
        [$manager, $reception] = $this->legacyActivityRoles();
        $before = $this->snapshot();
        $connection = DB::connection();
        $wait = (int) $connection->selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value;
        $armed = true;
        $observed = false;
        $connection->beforeExecuting(function (string $query) use ($connection, $manager, $reception, &$armed, &$observed): void {
            $sql = strtolower(str_replace(chr(96), '', $query));
            if (! $armed || ! str_starts_with($sql, 'insert into identity_feature_provisions')) {
                return;
            }
            $armed = false;
            // Same native transaction; inject a duplicate key so MySQL itself
            // rejects the intended marker INSERT after the real grants.
            $pdo = $connection->getPdo();
            $this->assertTrue($pdo->inTransaction());
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM role_has_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id IN (?, ?) AND p.name LIKE ?');
            $stmt->execute([$manager->id, $reception->id, 'activities.%']);
            $this->assertSame(7, (int) $stmt->fetchColumn());
            $stmt = $pdo->prepare('INSERT INTO identity_feature_provisions(feature_key, completed_at) VALUES (?, ?)');
            $stmt->execute([ActivityCatalogueGrants::FEATURE_KEY, '2026-11-26 00:00:00']);
            $observed = true;
        });
        app(PermissionRegistrar::class)->getPermissions();
        try {
            app(ProvisionActivityCatalogueAction::class)->execute();
            $this->fail('Expected actual duplicate marker rejection.');
        } catch (QueryException $failure) {
            $this->assertSame(1062, (int) $failure->errorInfo[1]);
        }
        $this->assertTrue($observed);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($wait, (int) $connection->selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value);
        $this->assertSame($before, $this->snapshot());
        $this->assertNull($this->marker());
        $this->assertTrue(app(ProvisionActivityCatalogueAction::class)->execute());
    }

    public function test_actual_commit_lost_acknowledgement_reconciles_marker_without_regrant(): void
    {
        [$manager, $reception] = $this->legacyActivityRoles();
        $connection = DB::connection();
        $oldId = (int) $connection->selectOne('SELECT CONNECTION_ID() AS id')->id;
        $fault = new AuthenticationCommitFault($connection->getPdo(), $connection);
        $connection->setPdo($fault);
        try {
            app(ProvisionActivityCatalogueAction::class)->execute();
            $this->fail('Lost acknowledgement must not be reported as acknowledged.');
        } catch (BusinessException $failure) {
            $this->assertSame(503, $failure->httpStatus);
            $this->assertSame('IDENTITY_ACTIVITY_PROVISION_UNCERTAIN', $failure->errorCode);
        }
        $this->assertSame(1, $fault->commits);
        $this->assertNull($connection->getRawPdo());
        $this->assertNotSame($oldId, (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
        $this->assertNotNull($this->marker());
        $this->assertEqualsCanonicalizing(ActivityPermissions::RECEPTION, $this->grantKeys($reception));
        foreach (ActivityPermissions::keys() as $key) {
            $this->assertContains($key, $this->grantKeys($manager));
        }
        $manager->fresh()->revokePermissionTo(Permission::findByName('activities.store', 'web'));
        $before = $this->snapshot();
        $this->assertFalse(app(ProvisionActivityCatalogueAction::class)->execute());
        $this->assertSame($before, $this->snapshot());
    }

    public function test_primary_access_reflects_default_grants_without_direct_grants(): void
    {
        [$manager, $reception] = $this->legacyActivityRoles();
        $managerUser = User::factory()->create();
        $managerUser->syncRoles([$manager]);
        $receptionUser = User::factory()->create();
        $receptionUser->syncRoles([$reception]);
        $access = app(IdentityAccess::class);
        $this->assertFalse($access->allows($managerUser->id, 'activities.store'));
        $this->assertFalse($access->allows($receptionUser->id, 'activities.index'));
        $this->assertTrue(app(ProvisionActivityCatalogueAction::class)->execute());
        foreach (ActivityPermissions::keys() as $key) {
            $this->assertTrue($access->allows($managerUser->id, $key));
            $this->assertSame(in_array($key, ActivityPermissions::RECEPTION, true), $access->allows($receptionUser->id, $key));
        }
        $this->assertSame(0, DB::table('model_has_permissions')->count());
        $this->assertSame(2, DB::table('model_has_roles')->count());
        $this->assertSame(1, (int) $managerUser->fresh()->auth_version);
        $this->assertSame(1, (int) $receptionUser->fresh()->auth_version);
    }

    private function marker(): ?array
    {
        $row = DB::table('identity_feature_provisions')->where('feature_key', ActivityCatalogueGrants::FEATURE_KEY)->first();

        return $row === null ? null : (array) $row;
    }

    private function snapshot(): array
    {
        $orders = [
            'roles' => ['id'], 'permissions' => ['id'], 'users' => ['id'], 'sessions' => ['id'],
            'role_has_permissions' => ['role_id', 'permission_id'], 'model_has_roles' => ['role_id', 'model_type', 'model_id'],
            'model_has_permissions' => ['permission_id', 'model_type', 'model_id'], 'identity_feature_provisions' => ['feature_key'],
        ];
        $result = [];
        foreach ($orders as $table => $columns) {
            $query = DB::table($table);
            foreach ($columns as $column) {
                $query->orderBy($column);
            }
            $result[$table] = $query->get()->map(static fn ($row) => (array) $row)->all();
        }

        return $result;
    }
}
