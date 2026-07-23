<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Application\BrowserSession;
use App\Modules\Identity\Domain\MfaStepUpPurpose;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ValueError;

/**
 * Enforce authentication level, freshness, and optional operation scope.
 */
final readonly class RequireAuthenticationAssurance
{
    /**
     * Create the assurance middleware with an injectable UTC clock.
     */
    public function __construct(private ClockInterface $clock) {}

    /**
     * Handle an incoming request and deny missing or stale assurance by default.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $requiredLevel = '2',
        string $freshnessMinutes = '10',
        ?string $purpose = null,
    ): Response {
        $account = $request->user('web');

        if (! $account instanceof IdentityAccount) {
            throw new AuthenticationException(guards: ['web']);
        }

        if (
            preg_match('/^[12]$/D', $requiredLevel) !== 1
            || preg_match('/^\d{1,2}$/D', $freshnessMinutes) !== 1
        ) {
            throw new AuthorizationException;
        }

        $required = (int) $requiredLevel;
        $freshness = (int) $freshnessMinutes;
        $actualLevel = $request->session()->get(BrowserSession::LEVEL);
        $authenticatedAt = $request->session()->get(BrowserSession::AUTHENTICATED_AT);

        if (
            $freshness < 1
            || $freshness > 15
            || ! is_int($actualLevel)
            || $actualLevel < $required
            || ! is_string($authenticatedAt)
        ) {
            throw new AuthorizationException;
        }

        try {
            $proofTime = CarbonImmutable::parse($authenticatedAt);
        } catch (Throwable) {
            throw new AuthorizationException;
        }

        $now = CarbonImmutable::instance($this->clock->now());

        if (
            $proofTime->isAfter($now->addMinute())
            || $proofTime->isBefore($now->subMinutes($freshness))
        ) {
            throw new AuthorizationException;
        }

        if ($required === 2 && $purpose !== null) {
            try {
                $requiredPurpose = MfaStepUpPurpose::from($purpose);
            } catch (ValueError) {
                throw new AuthorizationException;
            }

            $scope = $request->session()->get(BrowserSession::ASSURANCE_SCOPE);

            if ($scope !== 'all' && $scope !== $requiredPurpose->value) {
                throw new AuthorizationException;
            }
        }

        return $next($request);
    }
}
