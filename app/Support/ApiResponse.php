<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

final class ApiResponse
{
    public static function success(array $data = [], string $message = '', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message === '' ? '' : self::safeMessage($message), 'data' => $data], $status);
    }

    public static function error(string $message, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => self::safeMessage($message), 'errors' => $errors], $status, $headers);
    }

    public static function text(string $key): string
    {
        if (Lang::has($key, 'ar')) {
            $value = Lang::get($key, [], 'ar');
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        // Do not log arbitrary keys or repeat a warning on every error in one request.
        if (app()->bound('request') && ! request()->attributes->get('_missing_api_translation')) {
            request()->attributes->set('_missing_api_translation', true);
            Log::warning('Missing API translation.', ['request_id' => request()->attributes->get('_request_id')]);
        }

        return 'تعذر إتمام العملية. يرجى التواصل مع المسؤول.';
    }

    private static function safeMessage(string $message): string
    {
        // BusinessException callers provide system-owned translated messages.
        // A missing translation key is not safe public output.
        if ($message === '' || preg_match('/\A[A-Za-z0-9_:.-]+\z/', $message) === 1) {
            return self::text($message);
        }

        return $message;
    }
}
