<?php

use App\Http\Middleware\FirstPartyCors;
use App\Http\Middleware\ProtectSensitiveQueries;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\TrustedIdentityProxies;
use App\Support\ApiErrors;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([RequestId::class, ProtectSensitiveQueries::class]);
        $middleware->replace(TrustProxies::class, TrustedIdentityProxies::class);
        $middleware->replace(HandleCors::class, FirstPartyCors::class);
        $middleware->statefulApi();
        $middleware->prependToPriorityList(AuthenticatesRequests::class, RequireStatefulBrowser::class);
        $middleware->redirectGuestsTo(static fn () => null);
        $middleware->trimStrings(except: ['username', 'password', 'currentPassword', 'passwordConfirmation']);
        $middleware->convertEmptyStringsToNull(except: [fn (Request $request) => $request->is('api/v1/auth/login', 'api/v1/profile/password')]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['username', 'password', 'password_confirmation', 'current_password', 'currentPassword', 'passwordConfirmation']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception) => ApiErrors::applies($request) || $request->expectsJson());
        $exceptions->report(fn (Throwable $exception) => ApiErrors::report($exception));
        $exceptions->render(fn (Throwable $exception, Request $request) => ApiErrors::render($exception, $request));
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $response->headers->set('X-Request-ID', RequestId::id($request));

            return $response;
        });
    })->create();
