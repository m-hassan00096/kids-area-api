<?php

namespace App\Http\Middleware;

use App\Support\SensitiveQueryScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response;

final class ProtectSensitiveQueries
{
    public function handle(#[SensitiveParameter] Request $request, Closure $next): Response
    {
        // Credential/access operations in Identity need suppression, not every
        // business API. All Users endpoints exclusively handle staff PII, keys
        // or credentials. Role access-management queries are protected as well;
        // match the path before effective-method dispatch.
        // Native session I/O is independently protected by the
        // database session handler, including on future non-Identity routes.
        if (! $request->is('api/v1/auth/*', 'api/v1/profile/*', 'api/v1/users', 'api/v1/users/*', 'api/v1/roles', 'api/v1/roles/*')) {
            return $next($request);
        }

        return SensitiveQueryScope::run(DB::connection(), fn () => $next($request));
    }
}
