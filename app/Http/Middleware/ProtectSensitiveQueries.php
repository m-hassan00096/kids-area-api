<?php

namespace App\Http\Middleware;

use App\Support\SensitiveQueryScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ProtectSensitiveQueries
{
    public function handle(Request $request, Closure $next): Response
    {
        // Credential/access operations in Identity need suppression, not every
        // business API. Native session I/O is independently protected by the
        // database session handler, including on future non-Identity routes.
        if (! $request->is('api/v1/auth/*', 'api/v1/profile/*')) {
            return $next($request);
        }

        return SensitiveQueryScope::run(DB::connection(), fn () => $next($request));
    }
}
