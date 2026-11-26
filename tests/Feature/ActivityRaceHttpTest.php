<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Activities\Models\Activity;
use Modules\Identity\Models\Role;
use Tests\Support\ActivityHttpFixtures;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class ActivityRaceHttpTest extends AuthenticationHttpTestCase
{
    use ActivityHttpFixtures, AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_equivalent_name_creates_and_competing_renames_arbitrate_in_mysql(): void
    {
        $manager = $this->user('Activity.Race.Manager');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($manager->username)->status);
        $this->assertSame(200, $b->login($manager->username)->status);

        foreach ([['Swimming', 'swimming'], ['الرسم', 'الـرسم'], ['كرة قدم', "كرة\u{00A0}قدم"]] as [$name, $equivalent]) {
            [$first, $second] = $this->race(
                $a->prepare('POST', '/api/v1/activities', $this->activityFields($name, ['isActive' => false]), $this->raceOptions('activity-hold-insert', 'first')),
                $b->prepare('POST', '/api/v1/activities', $this->activityFields($equivalent), $this->raceOptions('activity-observe-mysql', 'second')),
                'manager',
            );
            $this->assertSame(201, $first->status);
            $this->assertDuplicate($second);
            $this->assertSame($name, Activity::query()->where('name', $equivalent)->sole()->name);
        }
        $this->assertSame(3, Activity::count());

        $target = $this->activityFixture('Rename target');
        [$first, $second] = $this->race(
            $a->prepare('POST', '/api/v1/activities', $this->activityFields('Create wins'), $this->raceOptions('activity-hold-insert', 'first')),
            $b->prepare('PUT', '/api/v1/activities/'.$target->id, $this->activityFields('CREATE WINS'), $this->raceOptions('activity-observe-mysql', 'second')),
            'manager',
        );
        $this->assertSame(201, $first->status);
        $this->assertDuplicate($second);
        $this->assertSame('Rename target', $target->fresh()->name);
        [$first, $second] = $this->race(
            $a->prepare('PUT', '/api/v1/activities/'.$target->id, $this->activityFields('Rename wins'), $this->raceOptions('activity-hold-update', 'first')),
            $b->prepare('POST', '/api/v1/activities', $this->activityFields('RENAME WINS'), $this->raceOptions('activity-observe-mysql', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertDuplicate($second);
        $this->assertSame('Rename wins', $target->fresh()->name);

        $other = $this->activityFixture('Other target');
        [$first, $second] = $this->race(
            $a->prepare('PUT', '/api/v1/activities/'.$target->id, $this->activityFields('Shared rename'), $this->raceOptions('activity-hold-update', 'first')),
            $b->prepare('PUT', '/api/v1/activities/'.$other->id, $this->activityFields('SHARED RENAME'), $this->raceOptions('activity-observe-mysql', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertDuplicate($second);
        $this->assertSame('Shared rename', $target->fresh()->name);
        $this->assertSame('Other target', $other->fresh()->name);
        $this->assertSame(6, Activity::count());
    }

    public function test_serial_update_delete_and_full_update_orders(): void
    {
        $manager = $this->user('Activity.Serial.Manager');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($manager->username)->status);
        $this->assertSame(200, $b->login($manager->username)->status);

        foreach ([
            ['PUT', 'DELETE', 200],
            ['DELETE', 'PUT', 404],
            ['DELETE', 'DELETE', 404],
            ['PUT', 'PUT', 200],
        ] as $i => [$one, $two, $secondStatus]) {
            $target = $this->activityFixture('Serial '.$i);
            $path = '/api/v1/activities/'.$target->id;
            $body = $this->activityFields('First '.$i, ['hourlyRate' => '10']);
            $replacement = $this->activityFields('Second '.$i, [
                'supportsHourly' => false, 'supportsSubscription' => true,
                'hourlyRate' => null, 'monthlyPrice' => '77.5', 'isActive' => false,
            ]);
            [$first, $second] = $this->race(
                $a->prepare($one, $path, $one === 'PUT' ? $body : null, $this->raceOptions($one === 'PUT' ? 'activity-hold-update' : 'activity-hold-delete', 'first')),
                $b->prepare($two, $path, $two === 'PUT' ? $replacement : null, $this->raceOptions('observe', 'second')),
                'manager',
            );
            $this->assertSame(200, $first->status);
            $this->assertSame($secondStatus, $second->status);
            $this->assertSame($secondStatus === 200 ? 'committed' : 'rolled_back', $second->header('X-Test-Activity-Outcome'));
            if ($one === 'DELETE' || $two === 'DELETE') {
                $this->assertFalse(Activity::query()->whereKey($target->id)->exists());
            } else {
                $row = $target->fresh();
                $this->assertSame('Second '.$i, $row->name);
                $this->assertFalse($row->supports_hourly);
                $this->assertNull($row->hourly_rate);
                $this->assertTrue($row->supports_subscription);
                $this->assertSame('77.50', $row->monthly_price);
                $this->assertFalse($row->is_active);
            }
        }
    }

    public function test_live_revocation_first_denies_and_authorized_write_first_commits(): void
    {
        $manager = $this->user('Activity.Revoke.Manager');
        $role = Role::create(['name' => 'Activity race writer', 'guard_name' => 'web']);
        $role->givePermissionTo('activities.update');
        $staff = $this->user('Activity.Revoke.Staff', $role->name);
        $target = $this->activityFixture('Protected by capability');
        $admin = $this->browser($this->server());
        $writer = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $writer->login($staff->username)->status);
        $rolePath = '/api/v1/roles/'.$role->id;
        $activityPath = '/api/v1/activities/'.$target->id;

        [$first, $second] = $this->race(
            $admin->prepare('PUT', $rolePath, ['name' => 'Activity revoked', 'isActive' => true, 'permissions' => []], $this->raceOptions('hold-role-write', 'first')),
            $writer->prepare('PUT', $activityPath, $this->activityFields('Must not commit'), $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(403, $second->status);
        $this->assertSame('rolled_back', $second->header('X-Test-Activity-Outcome'));
        $this->assertSame('0', $second->header('X-Test-Activity-Writes'));
        $this->assertSame('Protected by capability', $target->fresh()->name);
        $this->assertSame(200, $writer->me()->status);
        $this->assertSame([], $writer->me()->json()['data']['permissions']);

        $this->assertSame(200, $admin->request('PUT', $rolePath, [
            'name' => 'Activity restored', 'isActive' => true, 'permissions' => ['activities.update'],
        ])->status);
        [$first, $second] = $this->race(
            $writer->prepare('PUT', $activityPath, $this->activityFields('Authorized before revoke'), $this->raceOptions('activity-hold-update', 'first')),
            $admin->prepare('PUT', $rolePath, ['name' => 'Activity revoked again', 'isActive' => true, 'permissions' => []], $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertSame('Authorized before revoke', $target->fresh()->name);
        $this->assertSame(403, $writer->request('PUT', $activityPath, $this->activityFields('Must not commit'))->status);
        $this->assertSame($staff->auth_version, $staff->fresh()->auth_version);
    }

    public function test_role_disable_retires_admitted_waiting_write_and_does_not_restore_session(): void
    {
        $manager = $this->user('Activity.Disable.Manager');
        $role = Role::create(['name' => 'Activity disable writer', 'guard_name' => 'web']);
        $role->givePermissionTo('activities.update');
        $staff = $this->user('Activity.Disable.Staff', $role->name);
        $target = $this->activityFixture('Disable target');
        $admin = $this->browser($this->server());
        $writer = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $writer->login($staff->username)->status);

        [$first, $second] = $this->race(
            $admin->prepare('PUT', '/api/v1/roles/'.$role->id, ['name' => $role->name, 'isActive' => false, 'permissions' => ['activities.update']], $this->raceOptions('hold-role-write', 'first')),
            $writer->prepare('PUT', '/api/v1/activities/'.$target->id, $this->activityFields('Must not commit'), $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(401, $second->status);
        $this->assertSame('rolled_back', $second->header('X-Test-Activity-Outcome'));
        $this->assertSame('Disable target', $target->fresh()->name);
        $this->assertSame($staff->auth_version + 1, $staff->fresh()->auth_version);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
        $this->assertSame(200, $admin->request('PUT', '/api/v1/roles/'.$role->id, [
            'name' => $role->name, 'isActive' => true, 'permissions' => ['activities.update'],
        ])->status);
        $this->assertSame(401, $writer->me()->status);
    }

    private function assertDuplicate($response): void
    {
        $this->assertSame(409, $response->status);
        $this->assertSame('ACTIVITY_NAME_ALREADY_EXISTS', $response->json()['errors']['code']);
        $this->assertSame('1062', $response->header('X-Test-Activity-MySQL-Code'));
        $this->assertSame('1', $response->header('X-Test-Activity-MySQL-Count'));
        $this->assertSame('rolled_back', $response->header('X-Test-Activity-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Activity-Writes'));
    }
}
