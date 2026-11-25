<?php

namespace App\Http\Middleware;

use App\Support\BrowserOrigins;
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
        $api = BrowserOrigins::normalize((string) config('identity-browser.api_origin'));
        $allowed = config('identity-browser.frontend_origins', []);
        $origins = array_map(fn (string $origin) => BrowserOrigins::normalize($origin), $allowed);

        abort_if($api === null || in_array(null, $origins, true) || BrowserOrigins::normalize($request->getSchemeAndHttpHost()) !== $api, 403);
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

        $fromOrigin = $origin === null ? null : BrowserOrigins::normalize($origin);
        $fromReferer = $referer === null ? null : BrowserOrigins::normalize($referer, false);
        abort_if($origin !== null && ($fromOrigin === null || ! in_array($fromOrigin, $origins, true)), 403);
        abort_if($referer !== null && ($fromReferer === null || ! in_array($fromReferer, $origins, true)), 403);
        abort_if($fromOrigin !== null && $fromReferer !== null && $fromOrigin !== $fromReferer, 403);

        // Use the same canonical authority in the native Sanctum and CORS checks.
        // These staff routes do not consume the referring page path.
        if ($fromOrigin !== null) {
            $request->headers->set('Origin', $fromOrigin);
        }
        if ($fromReferer !== null) {
            $request->headers->set('Referer', $fromReferer.'/');
        }
    }
}
