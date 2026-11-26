<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\StaffSession;
use Modules\Identity\Support\IdentityPermissions;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class UsersAuthorityRaceTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_two_admins_cannot_remove_last_viable_path_and_stale_actor_loses_authority(): void
    {
        $founder = $this->user('Fixture.Founder', 'reception');
        $role = Role::create(['name' => 'fixture-viable', 'guard_name' => 'web']);
        $role->syncPermissions([...IdentityPermissions::ESSENTIAL, 'identity.users.delete']);
        $one = $this->user('Fixture.One', $role->name);
        $two = $this->user('Fixture.Two', $role->name);
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($one->username)->status);
        $this->assertSame(200, $b->login($two->username)->status);
        [$first, $second] = $this->race(
            $a->prepare('PUT', '/api/v1/users/'.$one->id, $this->fields($one, $role->name, false), $this->raceOptions('hold-users-update', 'first')),
            $b->prepare('PUT', '/api/v1/users/'.$two->id, $this->fields($two, 'reception'), $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(409, $second->status);
        $this->assertSame('IDENTITY_LAST_ADMIN', $second->json()['errors']['code']);
        $this->assertFalse($one->fresh()->is_active);
        $this->assertTrue($two->fresh()->is_active);
        $this->assertSame($role->id, $two->fresh()->roles()->sole()->id);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $b->me()->status);
        $this->assertSame('reception', $founder->fresh()->roles()->sole()->name);

        $this->assertSame(200, $b->request('PUT', '/api/v1/users/'.$one->id, $this->fields($one, $role->name))->status);
        $this->assertSame(200, $a->login($one->username)->status);
        $old = $a->sessionId();
        [$first, $second] = $this->race(
            $b->prepare('PUT', '/api/v1/users/'.$one->id, array_replace($this->fields($one, 'reception'), ['name' => 'Role changed']), $this->raceOptions('hold-users-update', 'first')),
            $a->prepare('DELETE', '/api/v1/users/'.$two->id, options: $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(403, $second->status);
        $this->assertNotNull($two->fresh());
        $this->assertSame($old, $a->sessionId());
        $this->assertSame(200, $a->me()->status);
        $this->assertSame('reception', $a->me()->json()['data']['role']['name']);
        $this->assertSame(200, $b->me()->status);
    }

    public function test_delete_first_revokes_waiting_actor_without_eliminating_remaining_admin(): void
    {
        $this->user('Fixture.Founder', 'reception');
        $one = $this->user('Fixture.One');
        $two = $this->user('Fixture.Two');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($one->username)->status);
        $this->assertSame(200, $b->login($two->username)->status);
        // A's delete and B's self-disable were both admitted before A commits.
        [$first, $second] = $this->race(
            $a->prepare('DELETE', '/api/v1/users/'.$two->id, options: $this->raceOptions('hold-users-delete-fk', 'first')),
            $b->prepare('PUT', '/api/v1/users/'.$two->id, $this->fields($two, 'manager', false), $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(401, $second->status);
        $this->assertNull($two->fresh());
        $this->assertTrue($one->fresh()->is_active);
        $this->assertSame(200, $a->me()->status);
        $this->assertSame(401, $b->me()->status);
    }

    public function test_delete_only_actor_and_self_disable_cannot_remove_last_admin_in_either_order(): void
    {
        $this->user('Fixture.Founder', 'reception');
        $one = $this->user('Fixture.One');
        $two = $this->user('Fixture.Two');
        $role = Role::create(['name' => 'fixture-delete-only', 'guard_name' => 'web']);
        $role->givePermissionTo('identity.users.delete');
        $deleter = $this->user('Fixture.Deleter', $role->name);
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $backup = $this->browser($b->server);
        $this->assertSame(200, $a->login($one->username)->status);
        $this->assertSame(200, $b->login($deleter->username)->status);
        $this->assertSame(200, $backup->login($two->username)->status);
        foreach ([true, false] as $disableFirst) {
            $disable = $a->prepare('PUT', '/api/v1/users/'.$one->id, $this->fields($one, 'manager', false),
                $this->raceOptions($disableFirst ? 'hold-users-update' : 'observe', $disableFirst ? 'first' : 'second'));
            $delete = $b->prepare('DELETE', '/api/v1/users/'.$two->id, options: $this->raceOptions($disableFirst ? 'observe' : 'hold-users-delete-fk', $disableFirst ? 'second' : 'first'));
            [$first, $second] = $disableFirst ? $this->race($disable, $delete, 'manager') : $this->race($delete, $disable, 'manager');
            $this->assertSame(200, $first->status);
            $this->assertSame(409, $second->status);
            $this->assertSame('IDENTITY_LAST_ADMIN', $second->json()['errors']['code']);
            $this->assertSame('rolled_back', $second->header('X-Test-Users-Outcome'));
            if ($disableFirst) {
                $this->assertFalse($one->fresh()->is_active);
                $this->assertTrue($two->fresh()->is_active);
                $this->assertSame(401, $a->me()->status);
                $this->assertSame(200, $backup->request('PUT', '/api/v1/users/'.$one->id, $this->fields($one, 'manager'))->status);
                $this->assertSame(200, $a->login($one->username)->status);
            } else {
                $this->assertNull($two->fresh());
                $this->assertTrue($one->fresh()->is_active);
                $this->assertSame(200, $a->me()->status);
                $this->assertSame(401, $backup->me()->status);
            }
            $this->assertSame(200, $b->me()->status);
        }
    }

    public function test_logout_first_mutation_first_and_idle_cutoff_recheck(): void
    {
        $actor = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($actor->username)->status);
        $b->cookies = $a->cookies;
        $path = '/api/v1/users/'.$target->id;
        $fields = array_replace($this->fields($target), ['name' => 'Must not commit']);
        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, $fields, $this->raceOptions('hold-manager', 'first')),
            $b->prepare('POST', '/api/v1/auth/logout', options: $this->raceOptions('observe', 'second')),
            null,
        );
        $this->assertSame(401, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $a->login($actor->username)->status);
        $b->cookies = $a->cookies;
        $fields['name'] = 'Committed before logout';
        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, $fields, $this->raceOptions('hold-users-update', 'first')),
            $b->prepare('POST', '/api/v1/auth/logout', options: $this->raceOptions('observe', 'second')),
            'session',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertSame($fields['name'], $target->fresh()->name);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $a->login($actor->username)->status);
        DB::table('sessions')->where('id', $a->sessionId())->update(['auth_last_activity' => intdiv($a->timeMs, 1000) - StaffSession::IDLE_SECONDS + 1]);
        $b->cookies = $a->cookies;
        // The test clock advances one second AFTER admission, before the
        // locked session recheck. The second independent request is unrelated.
        $fields['name'] = 'Expired before lock';
        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, $fields, $this->raceOptions('hold-manager-expire', 'first')),
            $b->prepare('GET', '/sanctum/csrf-cookie', options: $this->raceOptions('observe', 'second')),
            null,
        );
        $this->assertSame(401, $first->status);
        $this->assertSame(204, $second->status);
        $this->assertSame('Committed before logout', $target->fresh()->name);
        $this->assertSame(401, $a->me()->status);
    }
}
