<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationHttpResponse;

class UsersMutationTransactionTest extends AuthenticationHttpTestCase
{
    public function test_commit_rollback_last_admin_and_permanent_roles_with_real_mysql(): void
    {
        $actor = $this->user('Fixture.Admin');
        $target = $this->user('Fixture.Target', 'reception');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        foreach (['rollback' => 500, 'last-admin' => 409, 'permanent' => 409, 'duplicate' => 422] as $mode => $status) {
            $response = $this->mutation($browser, $target, $mode);
            $this->assertBoundary($response, $status, 'rolled_back', 'rolled_back', '1');
            $this->assertSame('9', $response->header('X-Test-Mutation-Wait'));
            $this->assertSame($target->name, $target->fresh()->name);
            $this->assertSame($target->username, $target->fresh()->username);
            $this->assertTrue($actor->fresh()->is_active);
            $this->assertTrue(Role::findByName('reception', 'web')->is_active);
            $this->assertSame(200, $browser->me()->status);
            if ($mode === 'duplicate') {
                $this->assertArrayHasKey('username', $response->json()['errors']);
            }
        }
        $response = $this->mutation($browser, $target, 'success');
        $this->assertBoundary($response, 200, 'committed', 'committed', '1');
        $this->assertSame('9', $response->header('X-Test-Mutation-Wait'));
        $this->assertSame('Synthetic boundary changed', $target->fresh()->name);
        $this->assertSame(200, $browser->me()->status);
    }

    public function test_lost_commit_ack_is_not_retried_or_misclassified_as_rollback(): void
    {
        $actor = $this->user('Fixture.Admin');
        $target = $this->user('Fixture.Target', 'reception');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $response = $this->mutation($browser, $target, 'ack');
        $this->assertBoundary($response, 503, 'unknown', 'unknown', '1');
        $this->assertSame('1', $response->header('X-Test-Actual-Commit-Count'));
        $this->assertSame('Synthetic boundary changed', $target->fresh()->name);
        $this->assertSame(200, $browser->me()->status);
    }

    public function test_cache_and_timeout_restore_faults_preserve_known_database_outcome(): void
    {
        $actor = $this->user('Fixture.Admin');
        $target = $this->user('Fixture.Target', 'reception');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $response = $this->mutation($browser, $target, 'rollback-restore');
        $this->assertBoundary($response, 500, 'rolled_back', 'unknown', '1');
        $this->assertSame($target->name, $target->fresh()->name);
        foreach (['cache', 'restore'] as $mode) {
            $response = $this->mutation($browser, $target, $mode);
            $this->assertBoundary($response, 200, 'committed', 'unknown', '1');
            $this->assertSame('Synthetic boundary changed', $target->fresh()->name);
            $this->assertSame(200, $browser->me()->status);
        }
    }

    public function test_real_manager_lock_timeout_is_bounded_and_restores_setting(): void
    {
        $actor = $this->user('Fixture.Admin');
        $target = $this->user('Fixture.Target', 'reception');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        DB::beginTransaction();
        try {
            DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->lockForUpdate()->first();
            $start = microtime(true);
            $response = $this->mutation($browser, $target, 'success');
            $elapsed = microtime(true) - $start;
            $this->assertGreaterThanOrEqual(4.5, $elapsed);
            $this->assertLessThan(20, $elapsed);
            $this->assertBoundary($response, 503, 'rolled_back', 'rolled_back', '0');
            $this->assertSame('9', $response->header('X-Test-Mutation-Wait'));
        } finally {
            DB::rollBack();
        }
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertSame(200, $browser->me()->status);
    }

    public function test_current_session_rejects_idle_revoked_and_wrong_owner_after_admission(): void
    {
        $actor = $this->user('Fixture.Admin');
        $target = $this->user('Fixture.Target', 'reception');
        $server = $this->server();
        foreach (['stale', 'revoked', 'owner'] as $mode) {
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            $id = $browser->sessionId();
            $response = $this->mutation($browser, $target, $mode);
            $this->assertBoundary($response, 401, 'rolled_back', 'rolled_back', '0');
            $row = DB::table('sessions')->where('id', $id)->first();
            if ($mode === 'owner') {
                $this->assertNotNull($row);
                $this->assertSame($target->id, (int) $row->auth_user_id);
            } else {
                $this->assertNull($row);
            }
            $this->assertSame(401, $browser->me()->status);
            $this->assertSame($target->name, $target->fresh()->name);
        }
    }

    public function test_current_permission_loss_does_not_retire_still_valid_session(): void
    {
        $this->user('Fixture.Founder');
        $role = Role::create(['name' => 'fixture-editor', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findByName('identity.users.update', 'web'));
        $actor = $this->user('Fixture.Editor', $role->name);
        $target = $this->user('Fixture.Target', 'reception');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $id = $browser->sessionId();
        $response = $this->mutation($browser, $target, 'permission');
        $this->assertBoundary($response, 403, 'rolled_back', 'rolled_back', '0');
        $this->assertSame($id, $browser->sessionId());
        $this->assertSame($actor->id, (int) DB::table('sessions')->where('id', $id)->value('auth_user_id'));
        $this->assertSame(200, $browser->me()->status);
        $this->assertSame($target->name, $target->fresh()->name);
    }

    private function mutation(AuthenticationBrowser $browser, User $target, string $mode): AuthenticationHttpResponse
    {
        return $browser->request('PUT', '/api/v1/users/'.$target->id, options: [
            'raw' => '{}',
            'headers' => ['X-Identity-Test-Mode' => 'users-probe-mutation-'.$mode, 'X-Identity-Test-Inspect' => '1'],
        ]);
    }

    private function assertBoundary(AuthenticationHttpResponse $response, int $status, string $state, string $fileOutcome, string $calls): void
    {
        $this->assertSame($status, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        $this->assertSame($state, $response->header('X-Test-Mutation-State'));
        $this->assertSame($fileOutcome, $response->header('X-Test-Mutation-File-Outcome'));
        $this->assertSame($calls, $response->header('X-Test-Mutation-Calls'));
        $this->assertSame('1', $response->header('X-Test-Redacted'));
    }
}
