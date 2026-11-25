<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Modules\Identity\Services\IdentityAccess;
use Modules\Identity\Services\StaffSession;
use Symfony\Component\HttpFoundation\Response;

final class RequireStaffSession
{
    public function __construct(private readonly StaffSession $sessions, private readonly IdentityAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $at = $this->sessions->now();
        $binding = $this->sessions->requireBinding($request, $at);
        $context = $this->access->context((int) $binding->auth_user_id);
        if ($context === null) {
            $this->sessions->retire($request);
            abort(403);
        }

        // Revalidate the authoritative binding after the fresh access projection.
        $fresh = $this->sessions->requireBinding($request, $at);
        if ((int) $fresh->auth_version !== (int) $binding->auth_version) {
            $this->sessions->retire($request);
            throw new AuthenticationException;
        }

        $request->attributes->set('staff_binding', $binding);
        $request->attributes->set('staff_context', $context);
        $response = $next($request);
        if ($response->isSuccessful() && ! $request->is('api/v1/auth/logout', 'api/v1/profile/password')) {
            $this->sessions->touch($request, $binding, $at);
        }

        return $response;
    }
}
