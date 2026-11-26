<?php

use App\Support\BrowserOrigins;

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => BrowserOrigins::frontendOrigins((string) env('IDENTITY_FRONTEND_ORIGINS', '')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN', 'X-Requested-With'],
    'exposed_headers' => ['X-Request-ID', 'Retry-After', 'Allow'],
    'max_age' => 0,
    'supports_credentials' => true,
];
