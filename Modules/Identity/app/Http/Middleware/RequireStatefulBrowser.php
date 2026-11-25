<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Services\StaffSession;
use Symfony\Component\HttpFoundation\Response;

final class RequireStatefulBrowser
{
    public function __construct(private readonly StaffSession $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('sanctum') === true && $request->hasSession()
            && config('session.driver') === 'database' && config('session.encrypt') === true
            && config('session.connection') === null && config('session.table') === 'sessions', 403);

        // A failed native auth lookup must not leave an old authoritative
        // marker that a delayed payload write could later make usable.
        if (! $request->is('api/v1/auth/login') && $this->sessions->user($request) === null) {
            if ($request->session()->has(Auth::guard('web')->getName()) || $this->sessions->hasAuthorityMarker($request)) {
                $this->sessions->retire($request);
            }

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
