<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Load identity routes and configure credential-safe authentication rate limits.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Register identity routes, disable bearer-token parsing, and limit logins.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        Sanctum::getAccessTokenFromRequestUsing(
            static fn (Request $_request): ?string => null,
        );

        RateLimiter::for('auth.login', static function (Request $request): array {
            $email = $request->input('email');
            $normalizedEmail = is_string($email)
                ? Str::lower(trim($email))
                : 'invalid-identifier';
            $ipAddress = (string) ($request->ip() ?? 'unknown-address');

            return [
                Limit::perMinute(max(
                    1,
                    (int) config('identity.rate_limits.login_account_ip_per_minute'),
                ))->by('auth-login-account-ip:'.hash('sha256', $normalizedEmail.'|'.$ipAddress)),
                Limit::perMinute(max(
                    1,
                    (int) config('identity.rate_limits.login_ip_per_minute'),
                ))->by('auth-login-ip:'.hash('sha256', $ipAddress)),
            ];
        });

        RateLimiter::for('auth.mfa', static function (Request $request): Limit {
            $identity = (string) ($request->user('web')?->getAuthIdentifier() ?? 'guest');
            $ipAddress = (string) ($request->ip() ?? 'unknown-address');

            return Limit::perMinute(max(
                1,
                (int) config('identity.rate_limits.mfa_per_minute'),
            ))->by('auth-mfa:'.hash('sha256', $identity.'|'.$ipAddress));
        });
    }
}
