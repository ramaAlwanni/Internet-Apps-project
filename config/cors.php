<?php

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))
)));

return [
    /*
     * /instance is the endpoint the k6 tests call to prove which replica
     * served a request, so it must be CORS-enabled for the SPA as well.
     */
    'paths' => [
        'api/*',
        'instance',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
    ],

    'allowed_methods' => [
        '*',
    ],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        '*',
    ],

    'exposed_headers' => [
        'X-Backend-Instance',
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];
