<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

$origins = array_filter(array_map('trim', explode(',', (string) env('IDENTITY_FRONTEND_ORIGINS', ''))));

return [
    'stateful' => array_values(array_filter(array_map(static function (string $origin): ?string {
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);

        return is_string($host) && $host !== '' ? $host.($port === null ? '' : ':'.$port) : null;
    }, $origins))),
    'guard' => ['web'],
    'expiration' => null,
    'token_prefix' => '',
    'middleware' => [
        // Every protected staff route uses our stronger authoritative marker
        // and fresh credential generation check after the web guard.
        'authenticate_session' => null,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
