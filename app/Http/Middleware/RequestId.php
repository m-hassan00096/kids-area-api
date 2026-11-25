<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestId
{
    public static function id(Request $request): string
    {
        if (! $request->attributes->has('_request_id')) {
            $request->attributes->set('_request_id', (string) Str::uuid());
        }

        return $request->attributes->get('_request_id');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $id = self::id($request);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}
