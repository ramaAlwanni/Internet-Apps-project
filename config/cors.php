<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'instance',
    ],

    'allowed_methods' => ['*'],

    // Env-driven so the SPA origin can change without editing committed code.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Required so the browser can read the replica identifier added by
    // BackendInstanceHeader (proof of load distribution in the SPA console).
    'exposed_headers' => ['X-Backend-Instance'],

    'max_age' => 0,

    'supports_credentials' => false,
];
