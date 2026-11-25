<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ProtectSensitiveQueries
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*', 'sanctum/csrf-cookie')) {
            return $next($request);
        }

        // Wrap the whole native session lifecycle, not just controller writes.
        // No secondary database connection is used by this feature.
        $connection = DB::connection();
        $logging = $connection->logging();
        $dispatcher = $connection->getEventDispatcher();
        $connection->disableQueryLog();
        $connection->unsetEventDispatcher();

        try {
            return $next($request);
        } finally {
            if ($dispatcher !== null) {
                $connection->setEventDispatcher($dispatcher);
            }
            if ($logging) {
                $connection->enableQueryLog();
            }
        }
    }
}
