<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Activities\Models\Activity;
use Tests\Support\ActivityHttpFixtures;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class ActivityFaultHttpTest extends AuthenticationHttpTestCase
{
    use ActivityHttpFixtures, AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_real_insert_update_delete_roll_back_after_dml_and_logs_are_safe(): void
    {
        $actor = $this->user('Activity.Fault.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $target = $this->activityFixture('Rollback original');
        $path = '/api/v1/activities/'.$target->id;
        $before = $this->activitySnapshot();
        foreach ([
            ['POST', '/api/v1/activities', $this->activityFields('Rollback create'), 'activity-fault-after-insert'],
            ['PUT', $path, $this->activityFields('Rollback update'), 'activity-fault-after-update'],
            ['DELETE', $path, null, 'activity-fault-after-delete'],
        ] as [$method, $url, $body, $mode]) {
            $response = $admin->request($method, $url, $body, $this->inspectActivity($mode));
            $this->assertSame(500, $response->status);
            $this->assertSame(['success' => false, 'message' => __('api.errors.internal'), 'errors' => []], $response->json());
            $this->assertSame('rolled_back', $response->header('X-Test-Activity-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Activity-Writes'));
            $this->assertSame('2', $response->header('X-Test-Activity-Timeout-Statements'));
            $this->assertSame('1', $response->header('X-Test-Activity-Outcome-Reported'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame($before, $this->activitySnapshot());
        }
        $this->assertSame(200, $admin->me()->status);
    }

    public function test_actual_mysql_timeout_and_failed_restore_preserve_primary_outcome(): void
    {
        $actor = $this->user('Activity.Timeout.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $target = $this->activityFixture('Timeout original');
        $before = $this->activitySnapshot();

        DB::beginTransaction();
        try {
            DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->lockForUpdate()->first(['id']);
            $start = microtime(true);
            $response = $admin->request('PUT', '/api/v1/activities/'.$target->id, $this->activityFields('Must not commit'), $this->inspectActivity('activity-timeout-restore'));
            $elapsed = microtime(true) - $start;
            $this->assertGreaterThanOrEqual(4.5, $elapsed);
            $this->assertLessThan(20, $elapsed);
            $this->assertSame(503, $response->status);
            $this->assertSame('1205', $response->header('X-Test-Activity-MySQL-Code'));
            $this->assertSame('1', $response->header('X-Test-Activity-MySQL-Count'));
            $this->assertSame('rolled_back', $response->header('X-Test-Activity-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Activity-Restore-Warning'));
            $this->assertSame('0', $response->header('X-Test-Activity-Writes'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $this->activitySnapshot());
        $this->assertSame(200, $admin->me()->status);
    }

    public function test_actual_innodb_deadlock_refuses_write_once(): void
    {
        $actor = $this->user('Activity.Deadlock.Manager');
        $admin = $this->browser($this->server());
        $external = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $target = $this->activityFixture('Deadlock original');
        $before = $this->activitySnapshot();
        // Existing reviewed test-only inversion: external transaction holds a
        // user row plus undo entries, Activity locks manager then waits on user.
        [$first, $second] = $this->race(
            $external->prepare('POST', '/api/v1/__fixture-roles-deadlock/'.$actor->id, options: $this->raceOptions('hold-roles-deadlock', 'first')),
            $admin->prepare('PUT', '/api/v1/activities/'.$target->id, $this->activityFields('Must not commit'), $this->inspectActivity('activity-observe-mysql', 'second')),
            'users',
        );
        $this->assertSame(201, $first->status);
        $this->assertSame(503, $second->status);
        $this->assertSame('1213', $second->header('X-Test-Activity-MySQL-Code'));
        $this->assertSame('1', $second->header('X-Test-Activity-MySQL-Count'));
        $this->assertSame('rolled_back', $second->header('X-Test-Activity-Outcome'));
        $this->assertSame('0', $second->header('X-Test-Activity-Writes'));
        $this->assertSame('1', $second->header('X-Test-Redacted'));
        $this->assertSame($before, $this->activitySnapshot());
        $this->assertSame(100, DB::table('identity_login_throttles')->where('scope', 250)->count());
    }

    public function test_lost_commit_acknowledgement_and_postcommit_session_failure_never_replay(): void
    {
        $actor = $this->user('Activity.Commit.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $response = $admin->request('POST', '/api/v1/activities', $this->activityFields('Committed uncertain'), $this->inspectActivity('activity-commit-ack'));
        $this->assertSame(503, $response->status);
        $this->assertSame('unknown', $response->header('X-Test-Activity-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Actual-Commit-Count'));
        $this->assertSame('1', $response->header('X-Test-Activity-Writes'));
        $this->assertSame('1', $response->header('X-Test-Activity-Outcome-Reported'));
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $row = Activity::query()->where('name', 'Committed uncertain')->sole();
        $this->assertSame(1, Activity::count());
        $this->assertSame(200, $admin->request('GET', '/api/v1/activities/'.$row->id)->status);

        $response = $admin->request('PUT', '/api/v1/activities/'.$row->id, $this->activityFields('Committed before session fault'), $this->inspectActivity('activity-native-save-fault'));
        $this->assertSame(500, $response->status);
        $this->assertSame('committed', $response->header('X-Test-Activity-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Activity-Writes'));
        $this->assertSame('1', $response->header('X-Test-Activity-Outcome-Reported'));
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame('Committed before session fault', $row->fresh()->name);
        $this->assertSame(1, Activity::count());
        $this->assertSame(200, $admin->me()->status);
    }

    private function inspectActivity(string $mode, ?string $barrier = null): array
    {
        $headers = ['X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Inspect' => '1'];
        if ($barrier !== null) {
            $headers['X-Identity-Test-Barrier'] = $barrier;
        }

        return ['headers' => $headers];
    }
}
