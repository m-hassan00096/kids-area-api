<?php

namespace Modules\Identity\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use SensitiveParameter;

final class StaffSession
{
    public const IDLE_SECONDS = 43200;

    public function now(): int
    {
        return now()->getTimestamp();
    }

    public function user(Request $request): ?User
    {
        $guard = Auth::guard('web');
        $user = $guard->user();

        if (! $user instanceof User || (string) $request->session()->get($guard->getName()) !== (string) $user->id) {
            return null;
        }

        return $user;
    }

    public function binding(Request $request, int $at): ?object
    {
        $user = $this->user($request);
        if ($user === null) {
            return null;
        }

        return DB::table('sessions as s')->useWritePdo()
            ->join('users as u', 'u.id', '=', 's.auth_user_id')
            ->where('s.id', $request->session()->getId())
            ->where('s.auth_user_id', $user->id)
            ->whereColumn('s.auth_version', 'u.auth_version')
            ->where('s.auth_last_activity', '>', $at - self::IDLE_SECONDS)
            ->first(['s.auth_user_id', 's.auth_version', 's.auth_last_activity']);
    }

    public function hasAuthorityMarker(Request $request): bool
    {
        return DB::table('sessions')->useWritePdo()->where('id', $request->session()->getId())
            ->whereNotNull('auth_user_id')->exists();
    }

    public function requireBinding(Request $request, int $at): object
    {
        $binding = $this->binding($request, $at);
        if ($binding === null) {
            $this->retire($request);
            throw new AuthenticationException;
        }

        return $binding;
    }

    public function touch(Request $request, object $binding, int $at): void
    {
        DB::table('sessions')->where('id', $request->session()->getId())
            ->where('auth_user_id', $binding->auth_user_id)
            ->where('auth_version', $binding->auth_version)
            ->where('auth_last_activity', '>', $at - self::IDLE_SECONDS)
            ->update(['auth_last_activity' => DB::raw('GREATEST(auth_last_activity, '.(int) $at.')')]);
    }

    public function retire(Request $request): void
    {
        // The native store destroys exactly this ID and clears the browser state.
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function forgetAttempt(Request $request, #[SensitiveParameter] ?string $candidate, ?int $owner, ?int $version): void
    {
        // Delete only a candidate that was proven to belong to this attempt.
        // A random-ID collision must never destroy another browser's session.
        try {
            if ($candidate !== null && $owner !== null && $version !== null) {
                DB::table('sessions')->where('id', $candidate)
                    ->where('auth_user_id', $owner)->where('auth_version', $version)->delete();
            }
        } finally {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->flush();
            $request->session()->migrate(false);
            $request->session()->regenerateToken();
        }
    }
}
