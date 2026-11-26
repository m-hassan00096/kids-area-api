<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Models\Role;
use Symfony\Component\HttpFoundation\Response;

final class RequireRolesCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $context = $request->attributes->get('staff_context');
        abort_unless(is_array($context) && in_array($capability, $context['permissions'] ?? [], true), 403);

        $parameter = $request->route('role');
        if ($parameter !== null) {
            $maximum = (string) PHP_INT_MAX;
            abort_unless(is_string($parameter) && preg_match('/\A[1-9][0-9]*\z/', $parameter) === 1
                && (strlen($parameter) < strlen($maximum)
                    || (strlen($parameter) === strlen($maximum) && strcmp($parameter, $maximum) <= 0)), 404);

            // Admission lookup only after the independent capability check.
            $target = Role::query()->useWritePdo()->whereKey((int) $parameter)
                ->where('guard_name', 'web')->whereRaw('BINARY guard_name = ?', ['web'])->first(['id']);
            abort_if($target === null, 404);
            $request->attributes->set('roles_target_id', (int) $target->id);
        }

        return $next($request);
    }
}
