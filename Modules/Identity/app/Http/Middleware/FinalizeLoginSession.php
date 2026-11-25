<?php

namespace Modules\Identity\Http\Middleware;

use App\Http\Middleware\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class FinalizeLoginSession
{
    public const CANDIDATE = '_identity_login_candidate';

    public function handle(#[SensitiveParameter] Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/auth/login') || ! $request->isMethod('POST')) {
            return $next($request);
        }

        // This middleware is outside the native session pipeline. Laravel may
        // render an exception from its second save as a response rather than
        // rethrow it through the Action's transaction boundary.
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->retireCandidate($request);

            throw $exception;
        }

        if ($response->getStatusCode() !== 200 && $request->attributes->has(self::CANDIDATE)) {
            $this->retireCandidate($request);
            foreach ($response->headers->getCookies() as $cookie) {
                if (in_array($cookie->getName(), [config('session.cookie'), 'XSRF-TOKEN'], true)) {
                    $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
                }
            }
        }
        $request->attributes->remove(self::CANDIDATE);

        return $response;
    }

    private function retireCandidate(#[SensitiveParameter] Request $request): void
    {
        $candidate = $request->attributes->get(self::CANDIDATE);
        if ($candidate === null) {
            return;
        }

        try {
            DB::table('sessions')->where('id', $candidate['id'])
                ->where('auth_user_id', $candidate['owner'])
                ->where('auth_version', $candidate['version'])->delete();
        } catch (Throwable $exception) {
            // If the store is unreachable, no new authenticated cookie is
            // delivered on this failure; physical retirement is best effort.
            Log::error('Failed login candidate retirement unavailable.', [
                'request_id' => RequestId::id($request),
                'exception_class' => $exception::class,
            ]);
        } finally {
            $request->attributes->remove(self::CANDIDATE);
        }
    }
}
