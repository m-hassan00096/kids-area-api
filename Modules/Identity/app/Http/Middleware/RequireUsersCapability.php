<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class RequireUsersCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        // RequireStaffSession populated the fresh primary-backed context on
        // this original HTTP request. Never infer authority from a role name.
        $context = $request->attributes->get('staff_context');
        abort_unless(is_array($context) && in_array($capability, $context['permissions'] ?? [], true), 403);

        $parameter = $request->route('user');
        if ($parameter !== null) {
            $maximum = (string) PHP_INT_MAX;
            abort_unless(is_string($parameter) && preg_match('/\A[1-9][0-9]*\z/', $parameter) === 1
                && (strlen($parameter) < strlen($maximum)
                    || (strlen($parameter) === strlen($maximum) && strcmp($parameter, $maximum) <= 0)), 404);

            // Explicit lookup after authorization. Only select the approved
            // detail projection; mutation Actions must re-read under locks.
            $target = User::query()->useWritePdo()->whereKey((int) $parameter)
                ->first(['id', 'name', 'username', 'email', 'phone', 'is_active', 'avatar_key']);
            abort_if($target === null, 404);
            $request->attributes->set('users_target', $target);
        }

        return $next($request);
    }
}
