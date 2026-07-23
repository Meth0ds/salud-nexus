<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Domain\Identity\PublicIdGenerator;
use App\Shared\Infrastructure\Identity\RamseyPublicIdGenerator;
use App\Support\Health\DatabaseReadinessProbe;
use App\Support\Health\ReadinessProbe;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Register shared adapters and enforce production security invariants at startup.
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register shared application services in the container.
     */
    public function register(): void
    {
        $this->app->bind(ReadinessProbe::class, DatabaseReadinessProbe::class);
        $this->app->singleton(
            ClockInterface::class,
            static fn (): ClockInterface => new NativeClock('UTC'),
        );
        $this->app->bind(PublicIdGenerator::class, RamseyPublicIdGenerator::class);
    }

    /**
     * Configure strict models, trusted proxies, rate limits, and safety checks.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        TrustProxies::at(config('api.trusted_proxies', []));

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(
            (int) config('api.rate_limits.api_per_minute'),
        )->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('health', static fn (Request $request): Limit => Limit::perMinute(
            (int) config('api.rate_limits.health_per_minute'),
        )->by((string) $request->ip()));

        $this->assertProductionConfigurationIsSafe();
    }

    /**
     * Fail fast when production configuration weakens a required security control.
     */
    private function assertProductionConfigurationIsSafe(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        if (config()->boolean('app.debug')) {
            throw new LogicException('APP_DEBUG must be false in production.');
        }

        if (blank(config('app.key'))) {
            throw new LogicException('APP_KEY must be configured in production.');
        }

        if (! config()->boolean('session.secure')) {
            throw new LogicException('SESSION_SECURE_COOKIE must be true in production.');
        }

        if (! config()->boolean('session.encrypt') || ! config()->boolean('session.http_only')) {
            throw new LogicException('Production sessions must be encrypted and HttpOnly.');
        }

        if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            throw new LogicException('Production sessions must use SameSite=Lax or SameSite=Strict.');
        }

        if (! in_array(config('session.driver'), ['database', 'redis'], true)) {
            throw new LogicException('Production sessions must use the database or Redis driver.');
        }

        if (config('hashing.driver') !== 'argon2id') {
            throw new LogicException('Production passwords must use the Argon2id hashing driver.');
        }

        $sessionLifetime = (int) config('session.lifetime');

        if ($sessionLifetime < 5 || $sessionLifetime > 480) {
            throw new LogicException('Production session lifetime must be between 5 and 480 minutes.');
        }

        $applicationUrl = (string) config('app.url');

        if (! str_starts_with($applicationUrl, 'https://')) {
            throw new LogicException('APP_URL must use HTTPS in production.');
        }

        if (! config()->boolean('api.hsts.enabled')) {
            throw new LogicException('HSTS_ENABLED must be true in production after HTTPS is configured.');
        }

        $trustedProxies = config('api.trusted_proxies', []);

        if (! is_array($trustedProxies) || in_array('*', $trustedProxies, true)) {
            throw new LogicException('TRUSTED_PROXIES must never trust every proxy in production.');
        }

        $trustedHosts = config('api.trusted_hosts', []);
        $applicationHost = parse_url($applicationUrl, PHP_URL_HOST);

        if (! is_array($trustedHosts) || $trustedHosts === [] || ! is_string($applicationHost)) {
            throw new LogicException('TRUSTED_HOSTS must contain an explicit allowlist in production.');
        }

        $applicationHostIsTrusted = false;

        foreach ($trustedHosts as $pattern) {
            if (is_string($pattern) && preg_match('/'.$pattern.'/D', $applicationHost) === 1) {
                $applicationHostIsTrusted = true;

                break;
            }
        }

        if (! $applicationHostIsTrusted) {
            throw new LogicException('TRUSTED_HOSTS must include the APP_URL host.');
        }

        $origins = config('cors.allowed_origins', []);
        $statefulDomains = config('sanctum.stateful', []);

        if (! is_array($origins) || $origins === [] || in_array('*', $origins, true)) {
            throw new LogicException('CORS_ALLOWED_ORIGINS must contain an explicit allowlist in production.');
        }

        if (! is_array($statefulDomains) || $statefulDomains === []) {
            throw new LogicException('SANCTUM_STATEFUL_DOMAINS must contain an explicit allowlist in production.');
        }

        foreach ($statefulDomains as $statefulDomain) {
            if (
                ! is_string($statefulDomain)
                || $statefulDomain === ''
                || str_contains($statefulDomain, '*')
                || str_contains($statefulDomain, '://')
                || str_contains($statefulDomain, '/')
            ) {
                throw new LogicException('Every production Sanctum domain must be an exact host with an optional port.');
            }
        }

        foreach ($origins as $origin) {
            if (! is_string($origin) || ! str_starts_with($origin, 'https://')) {
                throw new LogicException('Every production CORS origin must use HTTPS.');
            }

            $originHost = parse_url($origin, PHP_URL_HOST);
            $originPort = parse_url($origin, PHP_URL_PORT);

            if (! is_string($originHost)) {
                throw new LogicException('Every production CORS origin must contain a valid host.');
            }

            $statefulOrigin = $originHost.(is_int($originPort) ? ':'.$originPort : '');

            if (! in_array($statefulOrigin, $statefulDomains, true)) {
                throw new LogicException('Every credentialed CORS origin must be a Sanctum stateful domain.');
            }
        }

        if (config('sanctum.guard') !== ['web'] || config('sanctum.routes') !== false) {
            throw new LogicException('Sanctum must use only the web guard and the versioned CSRF route.');
        }
    }
}
