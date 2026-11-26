<?php

namespace Modules\Activities\Support;

use App\Support\ApiResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ActivityInput
{
    /** Pure native-type validation, shared by HTTP validation and trusted write boundary. */
    public static function validate(array $data, bool $updating): array
    {
        $message = ApiResponse::text('api.errors.validation');
        if (array_diff(array_keys($data), ['name', 'supportsHourly', 'supportsSubscription', 'hourlyRate', 'monthlyPrice', 'isActive']) !== []) {
            throw ValidationException::withMessages(['request' => $message]);
        }

        $errors = [];
        $name = $data['name'] ?? null;
        if (! is_string($name) || ! mb_check_encoding($name, 'UTF-8')) {
            $errors['name'] = $message;
        } else {
            $name = Str::trim($name);
            if ($name === '' || mb_strlen($name) > 255) {
                $errors['name'] = $message;
            }
        }

        $hourly = $data['supportsHourly'] ?? null;
        $subscription = $data['supportsSubscription'] ?? null;
        $active = array_key_exists('isActive', $data) ? $data['isActive'] : ($updating ? null : true);
        foreach (['supportsHourly' => $hourly, 'supportsSubscription' => $subscription, 'isActive' => $active] as $field => $flag) {
            if (! is_bool($flag)) {
                $errors[$field] = $message;
            }
        }
        if ($hourly === false && $subscription === false) {
            $errors['supportsHourly'] = $message;
            $errors['supportsSubscription'] = $message;
        }

        $prices = [];
        foreach (['hourlyRate' => $hourly, 'monthlyPrice' => $subscription] as $field => $enabled) {
            $value = $data[$field] ?? null;
            $price = self::price($value);
            if (($enabled === true && $price === null) || ($enabled === false && $value !== null)) {
                $errors[$field] = $message;
            }
            $prices[$field] = $enabled === true ? $price : null;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => $name,
            'supportsHourly' => $hourly,
            'supportsSubscription' => $subscription,
            'hourlyRate' => $prices['hourlyRate'],
            'monthlyPrice' => $prices['monthlyPrice'],
            'isActive' => $active,
        ];
    }

    public static function attributes(array $data, bool $updating): array
    {
        $fields = self::validate($data, $updating);

        return [
            'name' => $fields['name'],
            'supports_hourly' => $fields['supportsHourly'],
            'supports_subscription' => $fields['supportsSubscription'],
            'hourly_rate' => $fields['hourlyRate'],
            'monthly_price' => $fields['monthlyPrice'],
            'is_active' => $fields['isActive'],
        ];
    }

    private static function price(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $value) !== 1) {
            return null;
        }

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        return $integer === '0' && $fraction === '00' ? null : $integer.'.'.$fraction;
    }
}
