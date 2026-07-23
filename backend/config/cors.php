<?php

declare(strict_types=1);

$allowedOrigins = array_values(array_filter(
    array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200,http://localhost:4300')),
    ),
    static fn (string $origin): bool => $origin !== '',
));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-Request-ID',
        'X-XSRF-TOKEN',
    ],
    'exposed_headers' => ['X-Request-ID'],
    'max_age' => (int) env('CORS_MAX_AGE', 600),
    'supports_credentials' => true,
];
