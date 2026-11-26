<?php

namespace Modules\Identity\Services;

use App\Support\SensitiveQueryScope;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Models\User;
use SensitiveParameter;

final readonly class CurrentStaffMutation
{
    public function __construct(private IdentityAccess $access, private StaffSession $sessions) {}

    /**
     * The permanent manager mutex must already be held. Re-read authority
     * using the original admitted request, then lock the initiating session.
     */
    public function lock(#[SensitiveParameter] Request $request, string $permission): array
    {
        if (DB::transactionLevel() !== 1 || $request !== request()) {
            throw new LogicException('Current staff check requires the original request and outer transaction.');
        }

        $binding = $request->attributes->get('staff_binding');
        $admitted = $this->sessions->user($request);
        if (! is_object($binding) || $admitted === null || $admitted->id !== (int) $binding->auth_user_id) {
            throw new AuthenticationException;
        }

        $actorId = $admitted->id;
        $allowed = $this->access->currentHasAll([$permission], $actorId);
        $actor = User::query()->whereKey($actorId)->lockForUpdate()->first(['id', 'auth_version', 'is_active']);
        $snapshot = $request->attributes->get('users_target');
        $target = null;
        if ($allowed && $permission !== 'identity.users.create' && $snapshot instanceof User) {
            $target = User::query()->whereKey($snapshot->id)->lockForUpdate()->first();
            if ($target !== null) {
                DB::table('model_has_roles')->where('model_type', $target->getMorphClass())
                    ->where('model_id', $target->id)->orderBy('role_id')->lockForUpdate()->get();
                DB::table('model_has_permissions')->where('model_type', $target->getMorphClass())
                    ->where('model_id', $target->id)->orderBy('permission_id')->lockForUpdate()->get();
            }
        }

        $row = SensitiveQueryScope::run(DB::connection(), fn () => DB::table('sessions')
            ->where('id', $request->session()->getId())->lockForUpdate()
            ->first(['auth_user_id', 'auth_version', 'auth_last_activity']));
        if ($actor === null || $row === null || $row->auth_user_id === null || $row->auth_version === null
            || $row->auth_last_activity === null || (int) $row->auth_user_id !== $actorId
            || (int) $row->auth_version !== (int) $binding->auth_version
            || $actor->auth_version !== (int) $binding->auth_version
            || (int) $row->auth_last_activity <= $this->sessions->now() - StaffSession::IDLE_SECONDS) {
            throw new AuthenticationException;
        }

        // A valid session without the requested permission is not revoked.
        abort_unless($allowed && $actor->is_active, 403);
        if ($permission !== 'identity.users.create') {
            abort_unless($target instanceof User, 404);
        }

        return [$actor, $target];
    }

    public function retireInvalid(#[SensitiveParameter] Request $request): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Invalid staff binding retirement must follow rollback.');
        }
        $binding = $request->attributes->get('staff_binding');
        try {
            if (is_object($binding)) {
                SensitiveQueryScope::run(DB::connection(), fn () => DB::table('sessions')
                    ->where('id', $request->session()->getId())->where('auth_user_id', $binding->auth_user_id)
                    ->where('auth_version', $binding->auth_version)->delete());
            }
        } finally {
            // Never destroy a different owner's row merely because its ID
            // appeared in a stale request. Clear only this browser's state.
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->flush();
            $request->session()->migrate(false);
            $request->session()->regenerateToken();
        }
    }
}
