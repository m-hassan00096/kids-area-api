<?php

namespace Modules\Activities\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Activities\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

final class RequireActivitiesCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $context = $request->attributes->get('staff_context');
        abort_unless(is_array($context) && in_array($capability, $context['permissions'] ?? [], true), 403);
        abort_if($request->getRealMethod() !== $request->getMethod(), 422);

        $parameter = $request->route('activity');
        if ($parameter !== null) {
            $maximum = (string) PHP_INT_MAX;
            abort_unless(is_string($parameter) && preg_match('/\A[1-9][0-9]*\z/', $parameter) === 1
                && (strlen($parameter) < strlen($maximum)
                    || (strlen($parameter) === strlen($maximum) && strcmp($parameter, $maximum) <= 0)), 404);
            // Admission is not final authority. Never look up a target before capability.
            $target = Activity::query()->useWritePdo()->whereKey((int) $parameter)->first(['id']);
            abort_if($target === null, 404);
            $request->attributes->set('activities_target_id', (int) $target->id);
        }

        return $next($request);
    }
}
