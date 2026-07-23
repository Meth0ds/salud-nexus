<?php

declare(strict_types=1);

$trustedHostPatterns = array_values(array_filter(array_map(
    static function (string $host): string {
        $host = trim($host);

        return $host === '' ? '' : '^'.preg_quote($host, '/').'$';
    },
    explode(',', (string) env('TRUSTED_HOSTS', 'localhost,127.0.0.1')),
)));

$trustedProxies = array_values(array_filter(array_map(
    static fn (string $proxy): string => trim($proxy),
    explode(',', (string) env('TRUSTED_PROXIES', '')),
)));

return [
    'name' => env('API_NAME', 'Salud Nexus API'),
    'version' => 'v1',

    'request_id' => [
        'header' => 'X-Request-ID',
    ],

    'trusted_hosts' => $trustedHostPatterns,
    'trusted_proxies' => $trustedProxies,

    'problem_type_base' => rtrim(
        (string) env('PROBLEM_TYPE_BASE', 'https://salud-nexus.example/problems'),
        '/',
    ),

    'rate_limits' => [
        'api_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
        'health_per_minute' => (int) env('HEALTH_RATE_LIMIT_PER_MINUTE', 60),
    ],

    'requests' => [
        'max_body_bytes' => (int) env('API_MAX_BODY_BYTES', 1_048_576),
    ],

    'hsts' => [
        'enabled' => (bool) env('HSTS_ENABLED', false),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => (bool) env('HSTS_PRELOAD', false),
    ],
];
