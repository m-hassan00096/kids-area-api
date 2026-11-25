<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Services\LoginThrottle;
use RuntimeException;

class AuthenticationThrottleTest extends AuthenticationHttpTestCase
{
    public function test_username_window_across_ips_and_ip_window_across_usernames(): void
    {
        $this->user('Exact.Admin');
        $server = $this->server(proxy: true);
        $browser = $this->browser($server);
        $start = $browser->timeMs;
        for ($i = 1; $i <= 5; $i++) {
            $browser->timeMs = $start + ($i - 1) * 1000;
            $this->assertSame(401, $browser->login('Exact.Admin', 'wrong-synthetic-password', ['headers' => ['X-Forwarded-For' => '198.51.100.'.$i]])->status);
        }
        $blocked = $browser->login('Exact.Admin', options: ['headers' => ['X-Forwarded-For' => '198.51.100.9']]);
        $this->assertSame(429, $blocked->status);
        $this->assertSame('60', $blocked->header('Retry-After'));
        $browser->timeMs += 59000;
        $this->assertSame('1', $browser->login('Exact.Admin', options: ['headers' => ['X-Forwarded-For' => '198.51.100.9']])->header('Retry-After'));
        $browser->timeMs += 1000;
        $this->assertSame(200, $browser->login('Exact.Admin', options: ['headers' => ['X-Forwarded-For' => '198.51.100.9']])->status);

        $second = $this->browser($server);
        $second->timeMs = $browser->timeMs;
        $headers = ['X-Forwarded-For' => '203.0.113.12'];
        for ($i = 1; $i <= 30; $i++) {
            $this->assertSame(401, $second->login('Unknown.'.$i, options: ['headers' => $headers])->status);
        }
        $before = DB::table('identity_login_throttles')->where('scope', 1)->count();
        $response = $second->login('Unknown.NeverSeen', options: ['headers' => $headers]);
        $this->assertSame(429, $response->status);
        $this->assertSame('60', $response->header('Retry-After'));
        $this->assertSame($before, DB::table('identity_login_throttles')->where('scope', 1)->count());
        $this->assertSame(429, $second->login('Exact.Admin', options: ['headers' => $headers])->status);
        $this->assertSame(200, $second->login('Exact.Admin', options: ['headers' => ['X-Forwarded-For' => '203.0.113.13']])->status);
    }

    public function test_rolling_window_cleanup_and_success_does_not_clear_shared_ip(): void
    {
        $throttle = app(LoginThrottle::class);
        $start = now()->getTimestampMs();
        $ip = '198.51.100.42';
        $originalWait = (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value;
        try {
            Carbon::setTestNow(Carbon::createFromTimestampMs($start, 'UTC'));
            for ($i = 0; $i < 4; $i++) {
                $this->assertSame(401, $throttle->attempt('Exact.Admin', $ip, fn () => false)->status);
            }
            $this->assertSame(200, $throttle->attempt('Exact.Admin', $ip, fn () => true)->status);
            $row = DB::table('identity_login_throttles')->where('scope', 2)->where('key_digest', $throttle->digest(2, $ip))->first();
            $this->assertCount(4, json_decode($row->failure_times, true));
            $this->assertNotSame($throttle->digest(1, 'Exact.Admin'), $throttle->digest(1, 'exact.admin'));
            $this->assertSame($throttle->digest(2, '2001:db8::1'), $throttle->digest(2, '2001:0db8:0:0:0:0:0:1'));
            $this->assertSame($throttle->digest(2, '192.0.2.1'), $throttle->digest(2, '::ffff:192.0.2.1'));

            for ($i = 0; $i < 4; $i++) {
                $this->assertSame(401, $throttle->attempt('Exact.Admin', $ip, fn () => false)->status);
            }
            Carbon::setTestNow(Carbon::createFromTimestampMs($start + 60000, 'UTC'));
            $this->assertSame(401, $throttle->attempt('Exact.Admin', $ip, fn () => false)->status);
            $row = DB::table('identity_login_throttles')->where('scope', 1)->where('key_digest', $throttle->digest(1, 'Exact.Admin'))->first();
            $this->assertCount(1, json_decode($row->failure_times, true));

            try {
                $throttle->attempt('Exact.Admin', $ip, function (): bool {
                    throw new RuntimeException('Synthetic verification failure.');
                });
                $this->fail('Expected rolled-back admission.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Synthetic verification failure.', $exception->getMessage());
            }
            $this->assertSame($originalWait, (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value);
            $row = DB::table('identity_login_throttles')->where('scope', 1)->where('key_digest', $throttle->digest(1, 'Exact.Admin'))->first();
            $this->assertCount(1, json_decode($row->failure_times, true));
            Carbon::setTestNow(Carbon::createFromTimestampMs($start + 120000, 'UTC'));
            $throttle->cleanup();
            $this->assertSame(0, DB::table('identity_login_throttles')->count());
        } finally {
            Carbon::setTestNow();
        }
    }
}
