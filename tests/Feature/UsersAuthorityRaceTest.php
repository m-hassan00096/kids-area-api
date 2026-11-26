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
        $role = Role::create(['name' => 'fixture-viable', 'guard_name' => 'web', 'alias_name' => 'Fixture']);
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
