<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use App\Http\Middleware\RequestId;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;
use LogicException;
use Modules\Identity\Support\LoginResult;
use PDOException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

final class LoginThrottle
{
    private const USERNAME = 1;

    private const IP = 2;

    private const WINDOW = 60000;

    private const USERNAME_LIMIT = 5;

    private const IP_LIMIT = 30;

    public function nowMs(): int
    {
        return now()->getTimestampMs();
    }

    public function digest(int $scope, #[SensitiveParameter] string $value): string
    {
        $secret = (string) config('app.key');
        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7), true);
        }
        if (! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('Authentication limiter key unavailable.');
        }

        if ($scope === self::IP) {
            $value = inet_pton($value);
            if ($value === false) {
                abort(400);
            }
            // Treat IPv4-mapped IPv6 and its IPv4 spelling as one client IP.
            if (strlen($value) === 16 && substr($value, 0, 12) === str_repeat(chr(0), 10).chr(255).chr(255)) {
                $value = substr($value, 12);
            }
        } elseif ($scope !== self::USERNAME) {
            throw new LogicException('Unsupported login throttle scope.');
        }

        $key = hash_hmac('sha256', 'kids-area/identity/login-throttle/v1', $secret, true);

        return hash_hmac('sha256', chr($scope).chr(0).$value, $key);
    }

    public function attempt(#[SensitiveParameter] string $username, #[SensitiveParameter] string $ip, Closure $verify): LoginResult
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Login must own the outer transaction.');
        }

        $ipDigest = $this->digest(self::IP, $ip);
        $usernameDigest = $this->digest(self::USERNAME, $username);
        $connection = DB::connection();
        $originalWait = null;
        $failure = null;
        try {
            $originalWait = (int) $connection->selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value', [], false)->value;
            $connection->statement('SET SESSION innodb_lock_wait_timeout = 5');
            $lottery = config('identity-browser.throttle_cleanup_lottery', [2, 100]);
            if ($lottery[0] > 0 && random_int(1, $lottery[1]) <= $lottery[0]) {
                $this->cleanup();
            }

            return $connection->transaction(function () use ($ipDigest, $usernameDigest, $verify): LoginResult {
                // Fixed global order across all attempts: IP before username.
                $ipRow = $this->lockRow(self::IP, $ipDigest, true);
                $ipState = $this->state($ipRow, self::IP_LIMIT, $this->nowMs());
                if ($ipState['blocked'] !== null) {
                    // Do not allocate a new username row under an IP block.
                    $existing = $this->lockRow(self::USERNAME, $usernameDigest, false);
                    $now = $this->nowMs();
                    $ipState = $this->state($ipRow, self::IP_LIMIT, $now);
                    $userState = $existing === null ? ['blocked' => null] : $this->state($existing, self::USERNAME_LIMIT, $now);
                    if ($ipState['blocked'] !== null || $userState['blocked'] !== null) {
                        return $this->blocked($ipState['blocked'], $userState['blocked'], $now);
                    }
                }

                $userRow = $this->lockRow(self::USERNAME, $usernameDigest, true);
                $now = $this->nowMs();
                $ipState = $this->state($ipRow, self::IP_LIMIT, $now);
                $userState = $this->state($userRow, self::USERNAME_LIMIT, $now);
                if ($ipState['blocked'] !== null || $userState['blocked'] !== null) {
                    return $this->blocked($ipState['blocked'], $userState['blocked'], $now);
                }

                // Hold both per-key rows through credential outcome and commit.
                $accepted = $verify();
                $now = $this->nowMs();
                $ipState = $this->state($ipRow, self::IP_LIMIT, $now);
                $userState = $this->state($userRow, self::USERNAME_LIMIT, $now);
                if ($accepted === true) {
                    $this->persist(self::USERNAME, $usernameDigest, [], null, $now);
                    $this->persist(self::IP, $ipDigest, $ipState['times'], null, $now);

                    return new LoginResult(200);
                }

                $this->failure(self::IP, $ipDigest, $ipState['times'], self::IP_LIMIT, $now);
                $this->failure(self::USERNAME, $usernameDigest, $userState['times'], self::USERNAME_LIMIT, $now);

                // Return an outcome instead of throwing a 401 inside the transaction.
                return new LoginResult(401);
            }, 1);
        } catch (Throwable $exception) {
            $failure = $exception;

            throw $this->publicFailure($exception);
        } finally {
            if ($originalWait !== null) {
                try {
                    $connection->statement('SET SESSION innodb_lock_wait_timeout = '.$originalWait);
                } catch (Throwable $restoreFailure) {
                    // A connection with an unknown session setting must not be
                    // reused. Preserve the original failure if there was one.
                    $connection->disconnect();
                    Log::warning('Login lock timeout restoration failed.', [
                        'request_id' => app()->bound('request') ? RequestId::id(request()) : null,
                        'exception_class' => $restoreFailure::class,
                    ]);
                    if ($failure === null) {
                        throw $this->publicFailure($restoreFailure);
                    }
                }
            }
        }
    }

    private function publicFailure(Throwable $exception): Throwable
    {
        if ($exception instanceof PDOException
            && in_array((int) ($exception->errorInfo[1] ?? 0), [1205, 1213, 2002, 2003, 2006, 2013], true)) {
            return new BusinessException(ApiResponse::text('api.errors.unavailable'), 503);
        }

        return $exception;
    }

    private function lockRow(int $scope, string $digest, bool $create): ?object
    {
        if ($create) {
            DB::table('identity_login_throttles')->upsert([[
                'scope' => $scope,
                'key_digest' => $digest,
                'failure_times' => '[]',
                'blocked_until_ms' => null,
                'expires_at_ms' => $this->nowMs() + self::WINDOW,
            ]], ['scope', 'key_digest'], ['key_digest']);
        }

        $row = DB::table('identity_login_throttles')->where('scope', $scope)
            ->where('key_digest', $digest)->lockForUpdate()->first();
        if ($create && $row === null) {
            throw new RuntimeException('Login throttle row unavailable.');
        }

        return $row;
    }

    private function state(object $row, int $limit, int $now): array
    {
        $times = json_decode($row->failure_times, true, 512, JSON_THROW_ON_ERROR);
        $blocked = $row->blocked_until_ms === null ? null : filter_var($row->blocked_until_ms, FILTER_VALIDATE_INT);
        $expiry = filter_var($row->expires_at_ms, FILTER_VALIDATE_INT);
        if (! is_array($times) || ! array_is_list($times) || count($times) > $limit
            || $blocked === false || ($blocked !== null && $blocked < 0)
            || $expiry === false || $expiry < 0 || $expiry > $now + self::WINDOW) {
            throw new RuntimeException('Invalid login throttle state.');
        }

        $previous = 0;
        foreach ($times as $time) {
            if (! is_int($time) || $time < $previous || $time > $now) {
                throw new RuntimeException('Invalid login throttle clock or state.');
            }
            $previous = $time;
        }

        // Validate the persisted relationship before pruning expired failures.
        // A corrupt deadline must not clear an otherwise active block.
        if (($times !== [] && ($expiry !== end($times) + self::WINDOW || end($times) - reset($times) >= self::WINDOW))
            || ($blocked !== null && (count($times) !== $limit || $blocked !== $expiry))
            || ($blocked === null && count($times) >= $limit)) {
            throw new RuntimeException('Inconsistent login throttle state.');
        }

        if ($blocked !== null) {
            if ($blocked > $now) {
                return ['times' => $times, 'blocked' => $blocked];
            }

            return ['times' => [], 'blocked' => null];
        }

        $times = array_values(array_filter($times, fn (int $time): bool => $time > $now - self::WINDOW));
        if (count($times) >= $limit) {
            throw new RuntimeException('Unblocked throttle state exceeded its limit.');
        }

        return ['times' => $times, 'blocked' => null];
    }

    private function blocked(?int $ipUntil, ?int $userUntil, int $now): LoginResult
    {
        return new LoginResult(429, max(1, (int) ceil((max($ipUntil ?? 0, $userUntil ?? 0) - $now) / 1000)));
    }

    private function failure(int $scope, string $digest, array $times, int $limit, int $now): void
    {
        $times[] = $now;
        $until = count($times) >= $limit ? $now + self::WINDOW : null;
        $this->persist($scope, $digest, $times, $until, $now);
    }

    private function persist(int $scope, string $digest, array $times, ?int $until, int $now): void
    {
        DB::table('identity_login_throttles')->where('scope', $scope)->where('key_digest', $digest)->update([
            'failure_times' => json_encode($times, JSON_THROW_ON_ERROR),
            'blocked_until_ms' => $until,
            'expires_at_ms' => $until ?? (($times === [] ? $now : end($times)) + self::WINDOW),
        ]);
    }

    public function cleanup(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Throttle cleanup cannot run inside login admission.');
        }

        $now = $this->nowMs();
        $rows = DB::table('identity_login_throttles')->useWritePdo()
            ->where('expires_at_ms', '<=', $now)->orderBy('expires_at_ms')->limit(100)
            ->get(['scope', 'key_digest']);
        $deadline = microtime(true) + 0.1;
        foreach ($rows as $row) {
            try {
                // One key per transaction; re-read and validate the complete
                // state before deleting so a corrupt expiry cannot erase a block.
                DB::transaction(function () use ($row): void {
                    $scope = (int) $row->scope;
                    $current = $this->lockRow($scope, $row->key_digest, false);
                    $at = $this->nowMs();
                    if ($current === null || (int) $current->expires_at_ms > $at) {
                        return;
                    }
                    if (! in_array($scope, [self::IP, self::USERNAME], true)) {
                        return;
                    }

                    try {
                        $this->state($current, $scope === self::IP ? self::IP_LIMIT : self::USERNAME_LIMIT, $at);
                    } catch (RuntimeException|JsonException) {
                        // Leave inconsistent rows for safe operator investigation.
                        Log::warning('Inconsistent login throttle cleanup candidate.', [
                            'request_id' => app()->bound('request') ? RequestId::id(request()) : null,
                        ]);

                        return;
                    }

                    DB::table('identity_login_throttles')->where('scope', $scope)
                        ->where('key_digest', $row->key_digest)->where('expires_at_ms', '<=', $at)->delete();
                }, 1);
            } catch (QueryException $exception) {
                if (! in_array((int) ($exception->errorInfo[1] ?? 0), [1205, 1213], true)) {
                    throw $exception;
                }

                Log::warning('Login throttle cleanup deferred due to contention.', [
                    'request_id' => app()->bound('request') ? RequestId::id(request()) : null,
                ]);

                break;
            }
            if (microtime(true) >= $deadline) {
                break;
            }
        }
    }
}
