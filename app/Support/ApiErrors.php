<?php

namespace App\Support;

use App\Exceptions\BusinessException;
use App\Http\Middleware\RequestId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Exceptions\InvalidFieldQuery;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidIncludeQuery;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiErrors
{
    public static function applies(Request $request): bool
    {
        return $request->is('api/*', 'sanctum/csrf-cookie');
    }

    private static function invalidQueryField(Throwable $exception): ?string
    {
        return match ($exception::class) {
            InvalidFilterQuery::class => 'filter',
            InvalidSortQuery::class => 'sort',
            InvalidIncludeQuery::class => 'include',
            InvalidFieldQuery::class => 'fields',
            default => null,
        };
    }

    public static function status(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException, self::invalidQueryField($exception) !== null => 422,
            $exception instanceof BusinessException => $exception->httpStatus,
            $exception instanceof AuthenticationException => 401,
            // Do not assume a new package exception is a recognized input failure.
            $exception instanceof InvalidQuery => 500,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }

    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! self::applies($request)) {
            return null;
        }

        $headers = [];
        $errors = [];
        if ($exception instanceof ValidationException) {
            $status = 422;
            foreach ($exception->errors() as $key => $messages) {
                $publicKey = implode('.', array_map(fn ($part) => Str::camel($part), explode('.', $key)));
                $errors[$publicKey] = array_map(fn ($message) => preg_match('/\p{Arabic}/u', $message) === 1
                    ? $message
                    : ApiResponse::text('api.errors.validation'), $messages);
            }
            $message = ApiResponse::text('api.errors.validation');
        } elseif (($field = self::invalidQueryField($exception)) !== null) {
            $status = 422;
            $message = ApiResponse::text('api.errors.validation');
            $errors = [$field => [$message]];
        } elseif ($exception instanceof BusinessException) {
            $status = $exception->httpStatus;
            $message = $exception->getMessage();
            if ($exception->errorCode !== null) {
                $errors = ['code' => $exception->errorCode];
            }
        } else {
            $status = self::status($exception);
            if ($exception instanceof HttpExceptionInterface && ! $exception instanceof InvalidQuery) {
                $headers = $exception->getHeaders();
            }
            $key = match ($status) {
                400 => 'bad_request',
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                405 => 'method_not_allowed',
                409 => 'conflict',
                413 => 'too_large',
                419 => 'csrf',
                422 => 'validation',
                429 => 'throttled',
                503 => 'unavailable',
                default => 'internal',
            };
            $message = ApiResponse::text('api.errors.'.$key);
        }

        return ApiResponse::error($message, $status, $errors, $headers);
    }

    public static function report(Throwable $exception): ?bool
    {
        if (! app()->bound('request') || ! self::applies(request())) {
            return null;
        }

        // Do not hand exception objects, request parameters, raw SQL or bindings
        // to Laravel's default exception logger on the API boundary.
        $status = self::status($exception);
        if ($status >= 500) {
            $context = [
                'request_id' => RequestId::id(request()),
                'exception_class' => $exception::class,
                'status' => $status,
            ];
            $outcome = request()->attributes->get('identity_users_outcome') ?? request()->attributes->get('identity_roles_outcome');
            if (in_array($outcome, ['committed', 'rolled_back', 'unknown'], true)) {
                $context['mutation_outcome'] = $outcome;
            }
            Log::error('API operation failed.', $context);
        }

        return false;
    }
}
