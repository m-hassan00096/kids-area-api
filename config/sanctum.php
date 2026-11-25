<?php

use App\Support\BrowserOrigins;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

$origins = BrowserOrigins::frontendOrigins((string) env('IDENTITY_FRONTEND_ORIGINS', ''));

return [
    'stateful' => BrowserOrigins::statefulHosts($origins),
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
