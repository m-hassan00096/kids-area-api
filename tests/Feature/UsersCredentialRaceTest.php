<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class UsersCredentialRaceTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_both_orders_of_login_against_disable_reset_and_delete(): void
    {
        $founder = $this->user('Fixture.Founder');
        $other = $this->user('Fixture.Other', 'reception');
        $admin = $this->browser($this->server());
        $unrelated = $this->browser($admin->server);
        $this->assertSame(200, $admin->login($founder->username)->status);
        $this->assertSame(200, $unrelated->login($other->username)->status);
        $loginServer = $this->server();
        foreach (['disable', 'reset', 'delete'] as $mode) {
            foreach ([true, false] as $loginFirst) {
                $target = $this->user('Fixture.'.$mode.($loginFirst ? '.First' : '.Second'), 'reception');
                $browser = $this->browser($loginServer);
                $oldDevice = $this->browser($admin->server);
                $this->assertSame(200, $oldDevice->login($target->username)->status);
                $path = '/api/v1/users/'.$target->id;
                $fields = $this->fields($target, 'reception', $mode !== 'disable');
                if ($mode === 'reset') {
                    $fields += ['password' => 'new-synthetic-race-password', 'passwordConfirmation' => 'new-synthetic-race-password'];
                    $fields['name'] = 'Updated by reset';
                }
                $mutation = $admin->prepare($mode === 'delete' ? 'DELETE' : 'PUT', $path, $mode === 'delete' ? null : $fields,
                    $this->raceOptions($loginFirst ? 'observe' : ($mode === 'delete' ? 'hold-users-delete-fk' : 'hold-users-update'), $loginFirst ? 'second' : 'first'));
                $login = $browser->prepare('POST', '/api/v1/auth/login', ['username' => $target->username, 'password' => 'test-fixture-only-password'],
                    $this->raceOptions($loginFirst ? 'hold-marker' : 'observe', $loginFirst ? 'first' : 'second'));
                [$first, $second] = $loginFirst
                    ? $this->race($login, $mutation, 'manager')
                    : $this->race($mutation, $login, 'manager');
                $this->assertSame(200, ($loginFirst ? $second : $first)->status);
                $this->assertSame($loginFirst ? 200 : 401, ($loginFirst ? $first : $second)->status);
                $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $target->id)->count());
                $this->assertSame(401, $browser->me()->status);
                $this->assertSame(401, $oldDevice->me()->status);
                if ($mode === 'delete') {
                    $this->assertNull($target->fresh());
                } else {
                    $this->assertSame(2, $target->fresh()->auth_version);
                    $this->assertSame($mode !== 'disable', $target->fresh()->is_active);
                    if ($mode === 'reset') {
                        $this->assertTrue(Hash::check('new-synthetic-race-password', $target->fresh()->password));
                    } else {
                        $this->assertSame($target->password, $target->fresh()->password);
                    }
                }
                $this->assertSame(200, $admin->me()->status);
                $this->assertSame(200, $unrelated->me()->status);
            }
        }
    }

    public function test_profile_and_admin_reset_both_commit_orders_keep_current_generation(): void
    {
        $founder = $this->user('Fixture.Founder');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($founder->username)->status);
        $server = $this->server();
        foreach ([true, false] as $profileFirst) {
            $target = $this->user('Fixture.Profile'.($profileFirst ? '.First' : '.Second'), 'reception');
            $profile = $this->browser($server);
            $this->assertSame(200, $profile->login($target->username)->status);
            $change = $profile->prepare('PUT', '/api/v1/profile/password', [
                'currentPassword' => 'test-fixture-only-password', 'password' => 'synthetic-profile-race',
                'passwordConfirmation' => 'synthetic-profile-race',
            ], $this->raceOptions($profileFirst ? 'hold-password' : 'observe', $profileFirst ? 'first' : 'second'));
            $reset = $admin->prepare('PUT', '/api/v1/users/'.$target->id, array_replace($this->fields($target), [
                'name' => 'Reset target', 'password' => 'synthetic-admin-race', 'passwordConfirmation' => 'synthetic-admin-race',
            ]), $this->raceOptions($profileFirst ? 'observe' : 'hold-users-update', $profileFirst ? 'second' : 'first'));
            [$first, $second] = $profileFirst
                ? $this->race($change, $reset, 'manager')
                : $this->race($reset, $change, 'manager');
            $this->assertSame(200, $first->status);
            $this->assertSame($profileFirst ? 200 : 401, $second->status);
            $this->assertSame($profileFirst ? 3 : 2, $target->fresh()->auth_version);
            $this->assertTrue(Hash::check('synthetic-admin-race', $target->fresh()->password));
            $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $target->id)->count());
            $this->assertSame(401, $profile->me()->status);
            $this->assertSame(200, $admin->me()->status);
        }
    }
}
