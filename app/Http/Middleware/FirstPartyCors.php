<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;

final class FirstPartyCors extends HandleCors
{
    public function handle($request, Closure $next)
    {
        if ($request->is('api/v1/auth/*', 'api/v1/profile/*', 'sanctum/csrf-cookie')) {
            $this->assertBrowser($request);
        }

        return parent::handle($request, $next);
    }

    private function assertBrowser(Request $request): void
    {
        $api = $this->origin((string) config('identity-browser.api_origin'), true);
        $allowed = config('identity-browser.frontend_origins', []);
        $origins = array_map(fn (string $origin) => $this->origin($origin, true), $allowed);

        abort_if($api === null || in_array(null, $origins, true) || $request->getSchemeAndHttpHost() !== $api, 403);
        if (! app()->environment('local', 'testing')) {
            abort_unless($request->isSecure() && config('session.secure') === true, 403);
            foreach ($origins as $configuredOrigin) {
                abort_unless(str_starts_with($configuredOrigin, 'https://'), 403);
            }
        }
        abort_if($request->headers->has('Authorization'), 403);

        $origin = $request->headers->get('Origin');
        $referer = $request->headers->get('Referer');
        abort_if($origin === null && $referer === null, 403);

        $fromOrigin = $origin === null ? null : $this->origin($origin, true);
        $fromReferer = $referer === null ? null : $this->origin($referer, false);
        abort_if($origin !== null && ($fromOrigin === null || ! in_array($fromOrigin, $origins, true)), 403);
        abort_if($referer !== null && ($fromReferer === null || ! in_array($fromReferer, $origins, true)), 403);
        abort_if($fromOrigin !== null && $fromReferer !== null && $fromOrigin !== $fromReferer, 403);
    }

    private function origin(string $value, bool $strict): ?string
    {
        $parts = parse_url($value);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])
            || ! in_array($parts['scheme'], ['https', 'http'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || str_contains($parts['host'], '*')
            || ($strict && (isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])))) {
            return null;
        }

        $port = $parts['port'] ?? null;
        if ($port === ($parts['scheme'] === 'https' ? 443 : 80)) {
            $port = null;
        }

        return $parts['scheme'].'://'.strtolower($parts['host']).($port === null ? '' : ':'.$port);
    }
}
