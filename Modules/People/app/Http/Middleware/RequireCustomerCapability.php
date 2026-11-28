<?php

namespace Modules\People\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireCustomerCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $context = $request->attributes->get('staff_context');
        abort_unless(is_array($context) && in_array($capability, $context['permissions'] ?? [], true), 403);
        abort_if($request->getRealMethod() !== $request->getMethod(), 422);

        $parameter = $request->route('customer');
        if ($parameter !== null) {
            $maximum = (string) PHP_INT_MAX;
            abort_unless(is_string($parameter) && preg_match('/\A[1-9][0-9]*\z/', $parameter) === 1
                && (strlen($parameter) < strlen($maximum)
                    || (strlen($parameter) === strlen($maximum) && strcmp($parameter, $maximum) <= 0)), 404);
            // Syntax only: no customer admission read. The authorized GET
            // reads once; PUT checks existence under its final write lock.
            $request->attributes->set('customers_target_id', (int) $parameter);
        }

        return $next($request);
    }
}
