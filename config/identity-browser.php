<?php

return [
    // Empty defaults fail closed until an environment supplies its own origins.
    'api_origin' => env('IDENTITY_API_ORIGIN', ''),
    'frontend_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('IDENTITY_FRONTEND_ORIGINS', ''))))),
    'throttle_cleanup_lottery' => [2, 100],
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('IDENTITY_TRUSTED_PROXIES', ''))))),
    // Synthetic non-account hash; no recoverable real staff credential.
    // If Argon work factors change, provision a matching synthetic hash.
    'dummy_hash' => env('IDENTITY_DUMMY_HASH') ?: '$argon2id$v=19$m=65536,t=4,p=1$RzZBZThuajc1dTNkUnpVSg$FOE1nrOB3ukQCnP412ieQZBvgVuSFOccnmX+++DxaT4',
];
