<?php

namespace Tests\Support;

use CurlMultiHandle;
use Illuminate\Support\Facades\DB;

// Shared existing deterministic HTTP barrier helper. The full Users race
// matrix and extra fault hooks remain separate later tasks.
trait AuthenticationRaceHarness
{
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
        // Each interleaving owns fresh barrier names even in a matrix test.
        foreach (['first.ready', 'first.held', 'first.release', 'second.ready'] as $name) {
            if (is_file($prefix.$name)) {
                unlink($prefix.$name);
            }
        }
        curl_multi_add_handle($multi, $a->handle);

        try {
            $this->await($multi, $done, fn () => is_file($prefix.'first.held'), 'First request did not reach barrier.');
            curl_multi_add_handle($multi, $b->handle);
            $this->await($multi, $done, fn () => is_file($prefix.'second.ready'), 'Second independent server did not start.');
            $firstId = json_decode(file_get_contents($prefix.'first.ready'), true)['connection_id'];
            $secondId = json_decode(file_get_contents($prefix.'second.ready'), true)['connection_id'];
            $this->assertNotSame($firstId, $secondId);
            $parentId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotSame($parentId, $secondId);
            $this->assertNotSame($parentId, $firstId);
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
                            'session' => str_contains($sql, 'from sessions') && str_contains($sql, 'for update'),
                            'users' => str_contains($sql, 'from users') && str_contains($sql, 'for update'),
                            'fk' => str_starts_with($sql, 'insert into fixture_user_links'),
                            'cleanup' => str_contains($sql, 'identity_login_throttles') && str_contains($sql, 'for update'),
                        };
                        if ($match) {
                            return true;
                        }
                    }

                    return false;
                }, 'Second MySQL connection was not observed on the contested query.', 4);
                $this->assertArrayNotHasKey(spl_object_id($b->handle), $done);
            } else {
                $this->await($multi, $done, function () use (&$done, $b): bool {
                    return isset($done[spl_object_id($b->handle)]);
                }, 'Second request did not commit while first was held.');
            }
            $this->assertArrayNotHasKey(spl_object_id($a->handle), $done);
            file_put_contents($prefix.'first.release', 'release');
            $this->await($multi, $done, function () use (&$done, $a, $b): bool {
                return isset($done[spl_object_id($a->handle)], $done[spl_object_id($b->handle)]);
            }, 'Requests did not finish after release.');
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
