<?php

namespace Modules\Identity\Actions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\Identity\Services\CredentialRevocation;
use Modules\Identity\Services\IdentityAccess;
use Modules\Identity\Services\IdentityMutex;
use Modules\Identity\Services\StaffSession;
use RuntimeException;
use SensitiveParameter;

final class ChangePasswordAction
{
    public function __construct(
        private readonly StaffSession $sessions,
        private readonly IdentityAccess $access,
        private readonly IdentityMutex $mutex,
        private readonly CredentialRevocation $revocation,
    ) {}

    public function execute(#[SensitiveParameter] Request $request, #[SensitiveParameter] string $currentPassword, #[SensitiveParameter] string $password): void
    {
        $this->mutex->ensureOuterTransactionAvailable();
        $binding = $this->sessions->requireBinding($request, $this->sessions->now());
        $userId = (int) $binding->auth_user_id;
        $snapshot = User::query()->useWritePdo()->find($userId);
        if ($snapshot === null || $snapshot->auth_version !== (int) $binding->auth_version) {
            $this->sessions->retire($request);
            throw new AuthenticationException;
        }
        if (! Hash::check($currentPassword, $snapshot->password)) {
            $this->sessions->requireBinding($request, $this->sessions->now());
            throw ValidationException::withMessages(['currentPassword' => ApiResponse::text('identity.incorrect_current_password')]);
        }

        $hash = Hash::make($password);
        $sessionId = $request->session()->getId();

        try {
            DB::transaction(function () use ($snapshot, $binding, $sessionId, $userId, $hash): void {
                $this->mutex->lock();
                if (! $this->access->currentHasAll([], $userId)) {
                    throw new AuthorizationException;
                }

                $locked = User::query()->whereKey($userId)->lockForUpdate()->first();
                $row = DB::table('sessions')->where('id', $sessionId)->lockForUpdate()->first();
                if ($locked === null || $row === null || (int) $row->auth_user_id !== $userId
                    || (int) $row->auth_version !== (int) $binding->auth_version
                    || $locked->auth_version !== (int) $binding->auth_version
                    || (int) $row->auth_last_activity <= $this->sessions->now() - StaffSession::IDLE_SECONDS) {
                    throw new AuthenticationException;
                }
                if ($locked->password !== $snapshot->password || $locked->auth_version !== $snapshot->auth_version) {
                    throw ValidationException::withMessages(['currentPassword' => ApiResponse::text('identity.incorrect_current_password')]);
                }

                if ($this->revocation->replace($locked, $hash) < 1) {
                    throw new RuntimeException('Required initiating session was not retired.');
                }
            }, 1);
        } catch (AuthenticationException|AuthorizationException $exception) {
            $this->sessions->retire($request);
            throw $exception;
        }

        // Never clear a valid browser before a known successful commit.
        $this->sessions->retire($request);
    }
}
