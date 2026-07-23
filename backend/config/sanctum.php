<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

$statefulDomains = array_values(array_filter(array_map(
    static fn (string $domain): string => trim($domain),
    explode(',', (string) env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:4200,localhost:4300,127.0.0.1,127.0.0.1:8000,127.0.0.1:4200,127.0.0.1:4300,::1'
            .Sanctum::currentApplicationUrlWithPort(),
    )),
), static fn (string $domain): bool => $domain !== ''));

return [
    'stateful' => $statefulDomains,
    'guard' => ['web'],

    // Personal access tokens are deliberately disabled in IdentityServiceProvider.
    'expiration' => 15,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'salud_nexus_'),

    // The versioned, rate-limited /api/v1/auth/csrf endpoint is used instead.
    'routes' => false,

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => PreventRequestForgery::class,
    ],
];
