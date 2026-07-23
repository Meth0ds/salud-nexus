<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\IdentityAccountStatus;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\Clock\ClockInterface;
use Throwable;

/**
 * Require a recent password proof before sensitive identity changes.
 */
final readonly class RequireRecentPasswordSession
{
    /**
     * Create the assurance guard with an injectable UTC clock.
     */
    public function __construct(private ClockInterface $clock) {}

    /**
     * Return the active account when its password proof is sufficiently recent.
     */
    public function handle(Request $request): IdentityAccount
    {
        $account = $request->user('web');

        if (
            ! $account instanceof IdentityAccount
            || $account->status !== IdentityAccountStatus::Active
        ) {
            throw new AuthenticationException(guards: ['web']);
        }

        $method = $request->session()->get(BrowserSession::METHOD);
        $level = $request->session()->get(BrowserSession::LEVEL);
        $passwordAuthenticatedAt = $request->session()->get(
            BrowserSession::PASSWORD_AUTHENTICATED_AT,
        );

        if (
            ! is_string($method)
            || ! str_starts_with($method, 'password')
            || ! is_int($level)
            || $level < 1
            || ! is_string($passwordAuthenticatedAt)
        ) {
            throw new AuthorizationException;
        }

        try {
            $proofTime = CarbonImmutable::parse($passwordAuthenticatedAt);
        } catch (Throwable) {
            throw new AuthorizationException;
        }

        $now = CarbonImmutable::instance($this->clock->now());
        $maximumAge = (int) config('identity.mfa.password_freshness_minutes');

        if (
            $maximumAge < 1
            || $maximumAge > 30
            || $proofTime->isAfter($now->addMinute())
            || $proofTime->isBefore($now->subMinutes($maximumAge))
        ) {
            throw new AuthorizationException;
        }

        return $account;
    }
}
