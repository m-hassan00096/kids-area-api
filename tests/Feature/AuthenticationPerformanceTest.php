<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\Support\AuthenticationPendingRequest;

class AuthenticationPerformanceTest extends AuthenticationHttpTestCase
{
    public function test_measure_small_staff_login_burst_and_shared_ip_failure_burst_at_default_hash_cost(): void
    {
        $servers = [];
        $browsers = [];
        $pending = [];
        for ($i = 1; $i <= 3; $i++) {
            $this->user('Perf.Staff'.$i, $i === 1 ? 'manager' : 'reception', [
                'password' => Hash::make('test-fixture-only-password', ['memory' => 65536, 'time' => 4, 'threads' => 1]),
            ]);
            $servers[] = $this->server(fullCost: true);
            $browsers[] = $this->browser($servers[$i - 1]);
            $browsers[$i - 1]->timeMs = $browsers[0]->timeMs;
            $pending[] = $browsers[$i - 1]->prepare('POST', '/api/v1/auth/login', [
                'username' => 'Perf.Staff'.$i,
                'password' => 'test-fixture-only-password',
            ]);
        }

        $valid = $this->burst($pending, 200);
        $meQueries = [];
        foreach ($browsers as $browser) {
            $response = $browser->me();
            $this->assertSame(200, $response->status);
            $meQueries[] = (int) $response->header('X-Test-Query-Count');
        }

        $failures = [];
        foreach ($servers as $server) {
            $guest = $this->browser($server);
            $guest->timeMs = $browsers[0]->timeMs;
            $failures[] = $guest;
        }
        $invalid = [];
        for ($wave = 0; $wave < 2; $wave++) {
            $pending = [];
            foreach ($failures as $i => $guest) {
                $pending[] = $guest->prepare('POST', '/api/v1/auth/login', [
                    'username' => 'Perf.Unknown'.($wave * 3 + $i),
                    'password' => 'wrong-synthetic-password',
                ]);
            }
            $invalid[] = $this->burst($pending, 401);
        }

        // Synthetic measurements only; no hard hosting SLA or raw credentials.
        fwrite(STDOUT, 'AUTH_PERF '.json_encode([
            'argon2id' => ['memory_kib' => 65536, 'time' => 4, 'threads' => 1],
            'three_staff_same_ip' => $valid,
            'two_waves_three_failures_same_ip' => $invalid,
            'me_query_counts' => $meQueries,
        ], JSON_THROW_ON_ERROR).PHP_EOL);
    }

    /**
     * @param  AuthenticationPendingRequest[]  $requests
     */
    private function burst(array $requests, int $expected): array
    {
        $multi = curl_multi_init();
        foreach ($requests as $request) {
            curl_multi_add_handle($multi, $request->handle);
        }
        $started = hrtime(true);
        try {
            $deadline = microtime(true) + 25;
            do {
                $status = curl_multi_exec($multi, $running);
                if ($status === CURLM_CALL_MULTI_PERFORM) {
                    continue;
                }
                $this->assertSame(CURLM_OK, $status);
                if ($running > 0) {
                    curl_multi_select($multi, 0.05);
                }
            } while ($running > 0 && microtime(true) < $deadline);
            $this->assertSame(0, $running);
            $elapsed = round((hrtime(true) - $started) / 1000000, 2);
            $measurements = [];
            foreach ($requests as $request) {
                $response = $request->finish(curl_multi_getcontent($request->handle));
                $this->assertSame($expected, $response->status);
                $measurements[] = [
                    'http_ms' => round(curl_getinfo($request->handle, CURLINFO_TOTAL_TIME) * 1000, 2),
                    'first_throttle_sql_ms' => (float) $response->header('X-Test-First-Throttle-Sql-Ms'),
                    'manager_span_upper_bound_ms' => (float) $response->header('X-Test-Manager-Span-Ms'),
                    'queries' => (int) $response->header('X-Test-Query-Count'),
                ];
            }

            return ['wall_ms' => $elapsed, 'requests_per_second' => round(count($requests) / ($elapsed / 1000), 2), 'requests' => $measurements];
        } finally {
            foreach ($requests as $request) {
                curl_multi_remove_handle($multi, $request->handle);
            }
            curl_multi_close($multi);
        }
    }
}
