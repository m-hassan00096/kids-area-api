<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireStatefulBrowser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('sanctum') === true && $request->hasSession()
            && config('session.driver') === 'database' && config('session.encrypt') === true
            && config('session.connection') === null && config('session.table') === 'sessions', 403);

        return $next($request);
    }
}
