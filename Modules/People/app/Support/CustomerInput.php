<?php

namespace Modules\People\Support;

use App\Support\ApiResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerInput
{
    /** The same canonical full-replacement fields serve HTTP and trusted writes. */
    public static function validate(array $data): array
    {
        $message = ApiResponse::text('api.errors.validation');
        if (array_diff(array_keys($data), ['name', 'mobile']) !== []) {
            throw ValidationException::withMessages(['request' => $message]);
        }

        $errors = [];
        $name = $data['name'] ?? null;
        if (! is_string($name) || ! mb_check_encoding($name, 'UTF-8')) {
            $errors['name'] = $message;
        } else {
            $name = Str::trim($name);
            if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
                $errors['name'] = $message;
            }
        }

        $mobile = $data['mobile'] ?? null;
        if ($mobile !== null) {
            if (! is_string($mobile) || ! mb_check_encoding($mobile, 'UTF-8')) {
                $errors['mobile'] = $message;
            } else {
                $mobile = Str::trim($mobile);
                if ($mobile === '') {
                    $mobile = null;
                } elseif (preg_match('/\A[0-9]{11}\z/', $mobile) !== 1) {
                    $errors['mobile'] = $message;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['name' => $name, 'mobile' => $mobile];
    }
}
