<?php

namespace Modules\Identity\Actions;

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Identity\Models\User;
use Modules\Identity\Services\IdentityAccess;
use Modules\Identity\Services\IdentityMutex;
use Modules\Identity\Services\LoginThrottle;
use Modules\Identity\Services\StaffSession;
use Modules\Identity\Support\LoginResult;
use RuntimeException;
use SensitiveParameter;
use Throwable;

final class LoginAction
{
    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly IdentityMutex $mutex,
        private readonly IdentityAccess $access,
        private readonly StaffSession $sessions,
    ) {}

    public function execute(#[SensitiveParameter] Request $request, #[SensitiveParameter] string $username, #[SensitiveParameter] string $password): LoginResult
    {
        $guard = Auth::guard('web');
        if ($guard->user() !== null || $request->session()->has($guard->getName())) {
            $existing = $this->sessions->binding($request, $this->sessions->now());
            if ($existing !== null && $this->access->isEligible((int) $existing->auth_user_id)) {
                throw new BusinessException(ApiResponse::text('identity.login_conflict'));
            }

            $this->sessions->retire($request);
            throw new AuthenticationException;
        }

        $candidate = null;
        $owner = null;
        $version = null;
        $attemptedSession = false;
        try {
            return $this->throttle->attempt($username, (string) $request->ip(), function () use ($request, $username, $password, $guard, &$candidate, &$owner, &$version, &$attemptedSession): bool {
                $dummy = config('identity-browser.dummy_hash');
                if (! is_string($dummy) || password_get_info($dummy)['algoName'] !== 'argon2id' || Hash::needsRehash($dummy)) {
                    throw new RuntimeException('Authentication dummy hash is not configured for the current work factors.');
                }

                $snapshot = User::query()->useWritePdo()->where('username', $username)->first();
                $realHash = $snapshot !== null && $snapshot->username === $username
                    && password_get_info($snapshot->password)['algoName'] === 'argon2id';
                $verified = Hash::check($password, $realHash ? $snapshot->password : $dummy);
                if (! $realHash || ! $verified || ! $this->access->isEligible($snapshot->id)) {
                    return false;
                }

                // Only a correct candidate enters the permanent manager mutex.
                $this->mutex->lock();
                if (! $this->access->currentHasAll([], $snapshot->id)) {
                    return false;
                }
                $locked = User::query()->whereKey($snapshot->id)->lockForUpdate()->first();
                if ($locked === null || $locked->username !== $username || $locked->password !== $snapshot->password
                    || $locked->auth_version !== $snapshot->auth_version) {
                    return false;
                }

                $oldId = $request->session()->getId();
                $attemptedSession = true;
                $guard->login($locked, false);
                $candidate = $request->session()->getId();
                if ($candidate === $oldId || DB::table('sessions')->where('id', $candidate)->lockForUpdate()->first() !== null) {
                    throw new RuntimeException('Unable to establish a new staff session.');
                }

                // Native Laravel encrypted Store/DatabaseSessionHandler, same transaction.
                $request->session()->save();
                $saved = DB::table('sessions')->where('id', $candidate)->lockForUpdate()->first();
                if ($saved === null || (int) $saved->user_id !== $locked->id || $saved->payload === ''
                    || $saved->auth_user_id !== null || $saved->auth_version !== null || $saved->auth_last_activity !== null) {
                    throw new RuntimeException('Native staff session was not persisted.');
                }

                $owner = $locked->id;
                $version = $locked->auth_version;
                $marked = DB::table('sessions')->where('id', $candidate)->whereNull('auth_user_id')->update([
                    'auth_user_id' => $owner,
                    'auth_version' => $version,
                    'auth_last_activity' => $this->sessions->now(),
                ]);
                if ($marked !== 1) {
                    throw new RuntimeException('Staff session binding was not initialized.');
                }

                return true;
            });
        } catch (Throwable $exception) {
            if ($attemptedSession) {
                try {
                    $this->sessions->forgetAttempt($request, $candidate, $owner, $version);
                } catch (Throwable $cleanup) {
                    // The client never receives a successful new cookie on failure.
                    Log::error('Staff login cleanup failed.', ['exception_class' => $cleanup::class]);
                }
            }

            throw $exception;
        }
    }
}
