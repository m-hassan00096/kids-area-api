<?php

namespace Tests\Feature;

use CurlMultiHandle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Services\LoginThrottle;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationPendingRequest;

class AuthenticationConcurrencyTest extends AuthenticationHttpTestCase
{
    public function test_overlapping_username_attempts_from_distinct_ips_cannot_exceed_five(): void
    {
        $this->user('Exact.Admin');
        $a = $this->browser($this->server(proxy: true));
        $b = $this->browser($this->server(proxy: true));
        $b->timeMs = $a->timeMs;
        for ($i = 1; $i <= 4; $i++) {
            $this->assertSame(401, $a->login('Exact.Admin', 'wrong-synthetic-password', ['headers' => ['X-Forwarded-For' => '198.51.100.'.$i]])->status);
        }

        [$first, $second] = $this->race(
            $this->login($a, 'Exact.Admin', 'wrong-synthetic-password', 'hold-credentials', 'first', '198.51.100.10'),
            $this->login($b, 'Exact.Admin', 'test-fixture-only-password', 'observe', 'second', '198.51.100.11'),
            'throttle',
        );
        $this->assertSame(401, $first->status);
        $this->assertSame(429, $second->status);
        $this->assertSame('60', $second->header('Retry-After'));
        $this->assertSame('0', $second->header('X-Test-Credential-Reads'));
        $row = DB::table('identity_login_throttles')->where('scope', 1)
            ->where('key_digest', app(LoginThrottle::class)->digest(1, 'Exact.Admin'))->first();
        $this->assertCount(5, json_decode($row->failure_times, true));
        $this->assertSame(0, DB::table('sessions')->whereNotNull('auth_user_id')->count());
    }

    public function test_overlapping_ip_attempts_cannot_exceed_thirty_or_allocate_new_blocked_identifier(): void
    {
        $this->user('Valid.Admin');
        $a = $this->browser($this->server(proxy: true));
        $b = $this->browser($this->server(proxy: true));
        $b->timeMs = $a->timeMs;
        $ip = '198.51.100.40';
        try {
            Carbon::setTestNow(Carbon::createFromTimestampMs($a->timeMs, 'UTC'));
            for ($i = 1; $i <= 29; $i++) {
                $this->assertSame(401, app(LoginThrottle::class)->attempt('Prior.'.$i, $ip, fn () => false)->status);
            }
        } finally {
            Carbon::setTestNow();
        }

        [$first, $second] = $this->race(
            $this->login($a, 'Thirtieth.Unknown', 'wrong-synthetic-password', 'hold-credentials', 'first', $ip),
            $this->login($b, 'Valid.Admin', 'test-fixture-only-password', 'observe', 'second', $ip),
            'throttle',
        );
        $this->assertSame(401, $first->status);
        $this->assertSame(429, $second->status);
        $this->assertSame('0', $second->header('X-Test-Credential-Reads'));
        $throttle = app(LoginThrottle::class);
        $this->assertFalse(DB::table('identity_login_throttles')->where('scope', 1)->where('key_digest', $throttle->digest(1, 'Valid.Admin'))->exists());
        $row = DB::table('identity_login_throttles')->where('scope', 2)->where('key_digest', $throttle->digest(2, $ip))->first();
        $this->assertCount(30, json_decode($row->failure_times, true));
    }

    public function test_old_hash_verified_before_concurrent_password_commit_cannot_login(): void
    {
        $staff = $this->user('Staff.Admin');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $a->timeMs = $b->timeMs;
        $this->assertSame(200, $b->login('Staff.Admin')->status);

        [$first, $second] = $this->race(
            $this->login($a, 'Staff.Admin', 'test-fixture-only-password', 'hold-manager', 'first'),
            $this->change($b, 'new-synthetic-password', 'observe', 'second'),
            null,
        );
        $this->assertSame(401, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertSame(2, $staff->fresh()->auth_version);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $a->login('Staff.Admin', 'new-synthetic-password')->status);
    }

    public function test_login_commit_first_is_revoked_by_waiting_password_change(): void
    {
        $staff = $this->user('Staff.Admin');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $a->timeMs = $b->timeMs;
        $this->assertSame(200, $b->login('Staff.Admin')->status);

        [$first, $second] = $this->race(
            $this->login($a, 'Staff.Admin', 'test-fixture-only-password', 'hold-marker', 'first'),
            $this->change($b, 'new-synthetic-password', 'observe', 'second'),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertSame(2, $staff->fresh()->auth_version);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
        $this->assertSame(401, $a->me()->status);
    }

    public function test_two_competing_password_changes_with_old_password_do_not_both_commit(): void
    {
        $staff = $this->user('Staff.Admin');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $b->timeMs = $a->timeMs;
        $this->assertSame(200, $a->login('Staff.Admin')->status);
        $this->assertSame(200, $b->login('Staff.Admin')->status);

        [$first, $second] = $this->race(
            $this->change($a, 'first-synthetic-password', 'hold-password', 'first'),
            $this->change($b, 'second-synthetic-password', 'observe', 'second'),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(401, $second->status);
        $this->assertSame(2, $staff->fresh()->auth_version);
        $this->assertTrue(Hash::check('first-synthetic-password', $staff->fresh()->password));
        $this->assertFalse(Hash::check('second-synthetic-password', $staff->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
    }

    public function test_native_late_insert_cannot_revive_logged_out_session(): void
    {
        $staff = $this->user('Staff.Admin');
        $a = $this->browser($this->server());
        $other = $this->browser($this->server());
        $other->timeMs = $a->timeMs;
        $this->assertSame(200, $a->login('Staff.Admin')->status);
        $this->assertSame(200, $other->login('Staff.Admin')->status);
        $b = clone $a;
        $b->server = $other->server;
        $oldId = $a->sessionId();

        [$first, $second] = $this->race(
            $a->prepare('GET', '/api/v1/auth/me', options: $this->options('hold-late-insert', 'first')),
            $b->prepare('POST', '/api/v1/auth/logout', options: $this->options('observe', 'second')),
            null,
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $row = DB::table('sessions')->where('id', $oldId)->first();
        $this->assertNotNull($row, 'The native late-insert path must actually be exercised.');
        $this->assertNull($row->auth_user_id);
        $this->assertNull($row->auth_version);
        $this->assertNull($row->auth_last_activity);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $other->me()->status);
        $this->assertSame(1, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
    }

    public function test_native_late_insert_after_password_change_is_never_authoritative(): void
    {
        $staff = $this->user('Staff.Admin');
        $otherUser = $this->user('Other.Staff', 'reception');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $b->timeMs = $a->timeMs;
        $unaffected = $this->browser($b->server);
        $unaffected->timeMs = $a->timeMs;
        $this->assertSame(200, $a->login('Staff.Admin')->status);
        $this->assertSame(200, $b->login('Staff.Admin')->status);
        $this->assertSame(200, $unaffected->login('Other.Staff')->status);
        $oldId = $a->sessionId();

        [$first, $second] = $this->race(
            $a->prepare('GET', '/api/v1/auth/me', options: $this->options('hold-late-insert', 'first')),
            $this->change($b, 'new-synthetic-password', 'observe', 'second'),
            null,
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $row = DB::table('sessions')->where('id', $oldId)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->auth_user_id);
        $this->assertSame(2, $staff->fresh()->auth_version);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $unaffected->me()->status);
        $this->assertSame(1, DB::table('sessions')->where('auth_user_id', $otherUser->id)->count());
    }

    public function test_expired_cleanup_rechecks_live_expiry_after_waiting_on_admission(): void
    {
        $a = $this->browser($this->server(proxy: true));
        $b = $this->browser($this->server(proxy: true));
        $ip = '198.51.100.65';
        try {
            Carbon::setTestNow(Carbon::createFromTimestampMs($a->timeMs - 60000, 'UTC'));
            $this->assertSame(401, app(LoginThrottle::class)->attempt('Expired.User', $ip, fn () => false)->status);
        } finally {
            Carbon::setTestNow();
        }
        $b->timeMs = $a->timeMs;
        [$first, $second] = $this->race(
            $this->login($a, 'Expired.User', 'wrong-synthetic-password', 'hold-credentials', 'first', $ip),
            $b->prepare('GET', '/sanctum/csrf-cookie', options: $this->options('cleanup', 'second')),
            'cleanup',
        );
        $this->assertSame(401, $first->status);
        $this->assertSame(204, $second->status);
        $row = DB::table('identity_login_throttles')->where('scope', 2)
            ->where('key_digest', app(LoginThrottle::class)->digest(2, $ip))->first();
        $this->assertNotNull($row);
        $this->assertCount(1, json_decode($row->failure_times, true));
        $this->assertSame($a->timeMs + 60000, (int) $row->expires_at_ms);
    }

    public function test_verified_lock_timeout_fails_closed_without_credential_work(): void
    {
        $this->user('Staff.Admin');
        $browser = $this->browser($this->server(proxy: true));
        $ip = '198.51.100.90';
        $throttle = app(LoginThrottle::class);
        $this->assertSame(401, $throttle->attempt('Prior.User', $ip, fn () => false)->status);
        $digest = $throttle->digest(2, $ip);
        DB::beginTransaction();
        try {
            DB::table('identity_login_throttles')->where('scope', 2)->where('key_digest', $digest)->lockForUpdate()->first();
            $response = $browser->login('Staff.Admin', options: ['headers' => ['X-Forwarded-For' => $ip, 'X-Identity-Test-Inspect' => '1']]);
            $this->assertSame(503, $response->status);
            $this->assertSame('0', $response->header('X-Test-Credential-Reads'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
        } finally {
            DB::rollBack();
        }
        $this->assertSame(0, DB::table('sessions')->whereNotNull('auth_user_id')->count());
        $this->assertFalse(DB::table('identity_login_throttles')->where('scope', 1)->where('key_digest', $throttle->digest(1, 'Staff.Admin'))->exists());
    }

    private function options(string $mode, string $barrier, ?string $ip = null): array
    {
        $headers = ['X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Barrier' => $barrier];
        if ($ip !== null) {
            $headers['X-Forwarded-For'] = $ip;
        }

        return ['headers' => $headers];
    }

    private function login(AuthenticationBrowser $browser, string $username, string $password, string $mode, string $barrier, ?string $ip = null): AuthenticationPendingRequest
    {
        return $browser->prepare('POST', '/api/v1/auth/login', ['username' => $username, 'password' => $password], $this->options($mode, $barrier, $ip));
    }

    private function change(AuthenticationBrowser $browser, string $password, string $mode, string $barrier): AuthenticationPendingRequest
    {
        return $browser->prepare('PUT', '/api/v1/profile/password', [
            'currentPassword' => 'test-fixture-only-password',
            'password' => $password,
            'passwordConfirmation' => $password,
        ], $this->options($mode, $barrier));
    }

    /**
     * Separate PHP HTTP servers/real MySQL connections. First holds a precise
     * native SQL boundary; second is observed waiting on SQL, or completes
     * before release for deliberately noncontended stale-read/save races.
     */
    private function race(AuthenticationPendingRequest $a, AuthenticationPendingRequest $b, ?string $contested): array
    {
        $multi = curl_multi_init();
        $done = [];
        $prefix = $this->barrierDir.DIRECTORY_SEPARATOR;
        curl_multi_add_handle($multi, $a->handle);

        try {
            $this->await($multi, $done, fn () => is_file($prefix.'first.held'), 'First request did not reach barrier.');
            curl_multi_add_handle($multi, $b->handle);
            $this->await($multi, $done, fn () => is_file($prefix.'second.ready'), 'Second independent server did not start.');
            $firstId = json_decode(file_get_contents($prefix.'first.ready'), true)['connection_id'];
            $secondId = json_decode(file_get_contents($prefix.'second.ready'), true)['connection_id'];
            $this->assertNotSame($firstId, $secondId);
            $this->assertNotSame((int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $secondId);
            if ($contested !== null) {
                $this->await($multi, $done, function () use ($secondId, $contested): bool {
                    foreach (DB::select('SHOW FULL PROCESSLIST') as $entry) {
                        $row = array_change_key_case((array) $entry);
                        if ((int) $row['id'] !== $secondId || ! in_array($row['command'], ['Query', 'Execute'], true)) {
                            continue;
                        }
                        $sql = strtolower(str_replace(chr(96), '', (string) $row['info']));
                        $match = match ($contested) {
                            'manager' => str_contains($sql, 'from roles') && str_contains($sql, 'for update'),
                            'throttle' => str_contains($sql, 'identity_login_throttles') && (str_starts_with($sql, 'insert') || str_contains($sql, 'for update')),
                            'cleanup' => str_starts_with($sql, 'delete from identity_login_throttles'),
                        };
                        if ($match) {
                            return true;
                        }
                    }

                    return false;
                }, 'Second MySQL connection was not observed on the contested query.', 4);
                $this->assertArrayNotHasKey(spl_object_id($b->handle), $done);
            } else {
                $this->await($multi, $done, function () use (&$done, $b): bool { return isset($done[spl_object_id($b->handle)]); }, 'Second request did not commit while first was held.');
            }
            $this->assertArrayNotHasKey(spl_object_id($a->handle), $done);
            file_put_contents($prefix.'first.release', 'release');
            $this->await($multi, $done, function () use (&$done, $a, $b): bool { return isset($done[spl_object_id($a->handle)], $done[spl_object_id($b->handle)]); }, 'Requests did not finish after release.');
            $this->assertSame(CURLE_OK, $done[spl_object_id($a->handle)]);
            $this->assertSame(CURLE_OK, $done[spl_object_id($b->handle)]);

            return [$a->finish(curl_multi_getcontent($a->handle)), $b->finish(curl_multi_getcontent($b->handle))];
        } finally {
            file_put_contents($prefix.'first.release', 'release');
            curl_multi_remove_handle($multi, $a->handle);
            curl_multi_remove_handle($multi, $b->handle);
            curl_multi_close($multi);
        }
    }

    private function await(CurlMultiHandle $multi, array &$done, callable $condition, string $failure, int $timeout = 7): void
    {
        $deadline = microtime(true) + $timeout;
        do {
            do {
                $status = curl_multi_exec($multi, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);
            $this->assertSame(CURLM_OK, $status);
            while ($message = curl_multi_info_read($multi)) {
                if ($message['msg'] === CURLMSG_DONE) {
                    $done[spl_object_id($message['handle'])] = $message['result'];
                }
            }
            clearstatcache();
            if ($condition()) {
                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        $this->fail($failure);
    }
}
