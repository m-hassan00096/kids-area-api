<?php

namespace Tests\Feature;

use Modules\Identity\Models\Role;
use Modules\Identity\Support\IdentityPermissions;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class RolesCacheTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_separate_worker_repopulates_native_file_cache_before_commit_and_is_evicted_afterwards(): void
    {
        $manager = $this->user('Fixture.CacheManager');
        $role = Role::create(['name' => 'Fixture Cache Staff', 'guard_name' => 'web']);
        $role->givePermissionTo('identity.roles.show');
        $staff = $this->user('Fixture.CacheStaff', $role->name);
        $admin = $this->browser($this->server());
        $observer = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $observer->login($staff->username)->status);
        $probe = '/api/v1/__fixture-roles-cache/'.$role->id;
        $warm = $observer->request('GET', $probe, options: $this->raceOptions('roles-cache-probe', 'warm'));
        $this->assertSame(200, $warm->status);
        $this->assertFalse($warm->json()['cachedBefore']);
        $this->assertTrue($warm->json()['cachedAfter']);
        $this->assertTrue($warm->json()['granted']);

        [$first, $second] = $this->race(
            $admin->prepare('PUT', '/api/v1/roles/'.$role->id, [
                'name' => $role->name, 'isActive' => true, 'permissions' => ['identity.users.view'],
            ], $this->raceOptions('roles-cache-hold-commit', 'first')),
            $observer->prepare('GET', $probe, options: $this->raceOptions('roles-cache-probe', 'second')),
            null,
        );
        $this->assertSame(200, $first->status);
        $this->assertSame('committed', $first->header('X-Test-Roles-Outcome'));
        $this->assertSame(200, $second->status);
        $this->assertFalse($second->json()['cachedBefore'], 'Native package must have evicted before commit.');
        $this->assertTrue($second->json()['cachedAfter']);
        $this->assertTrue($second->json()['granted'], 'Observer repopulated old committed grants while mutation was held.');
        $after = $observer->request('GET', $probe, options: $this->raceOptions('roles-cache-probe', 'after'));
        $this->assertSame(200, $after->status);
        $this->assertFalse($after->json()['cachedBefore'], 'Role boundary must evict the separately repopulated cache after commit.');
        $this->assertTrue($after->json()['cachedAfter']);
        $this->assertFalse($after->json()['granted']);
        $this->assertSame(['identity.users.view'], $observer->me()->json()['data']['permissions']);
        $this->assertSame(403, $observer->request('GET', '/api/v1/roles/'.$role->id)->status);
        $this->assertSame($staff->auth_version, $staff->fresh()->auth_version);
    }

    public function test_separate_worker_repopulates_native_file_cache_before_rollback_and_is_evicted_afterwards(): void
    {
        $manager = $this->user('Fixture.CacheLastAdmin');
        $role = Role::findByName('manager', 'web');
        $admin = $this->browser($this->server());
        $observer = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $probe = '/api/v1/__fixture-roles-cache/'.$role->id;
        $warm = $observer->request('GET', $probe, options: $this->raceOptions('roles-cache-probe', 'warm'));
        $this->assertSame(200, $warm->status);
        $this->assertTrue($warm->json()['granted']);
        $before = $role->permissions()->orderBy('permissions.name')->pluck('permissions.name')->all();
        [$first, $second] = $this->race(
            $admin->prepare('PUT', '/api/v1/roles/'.$role->id, [
                'name' => 'manager', 'isActive' => true,
                'permissions' => array_values(array_diff($before, ['identity.roles.show'])),
            ], $this->raceOptions('roles-cache-hold-rollback', 'first')),
            $observer->prepare('GET', $probe, options: $this->raceOptions('roles-cache-probe', 'second')),
            null,
        );
        $this->assertSame(409, $first->status);
        $this->assertSame('IDENTITY_LAST_ADMIN', $first->json()['errors']['code']);
        $this->assertSame('rolled_back', $first->header('X-Test-Roles-Outcome'));
        $this->assertSame(200, $second->status);
        $this->assertFalse($second->json()['cachedBefore']);
        $this->assertTrue($second->json()['cachedAfter']);
        $this->assertTrue($second->json()['granted']);
        $after = $observer->request('GET', $probe, options: $this->raceOptions('roles-cache-probe', 'after'));
        $this->assertSame(200, $after->status);
        $this->assertFalse($after->json()['cachedBefore'], 'Role boundary must evict after rollback.');
        $this->assertTrue($after->json()['cachedAfter']);
        $this->assertTrue($after->json()['granted']);
        $this->assertSame($before, $role->permissions()->orderBy('permissions.name')->pluck('permissions.name')->all());
        $this->assertSame(200, $admin->me()->status);
        foreach (IdentityPermissions::ESSENTIAL as $key) {
            $this->assertContains($key, $admin->me()->json()['data']['permissions']);
        }
    }
}
