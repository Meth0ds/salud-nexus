<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

/**
 * Distinguish an established AAL1 session from a pending MFA challenge.
 */
final readonly class PasswordAuthenticationOutcome
{
    /**
     * Create a password authentication outcome.
     */
    public function __construct(public ?PendingMfaChallenge $challenge) {}

    /**
     * Determine whether the browser must complete a second factor.
     */
    public function requiresMfa(): bool
    {
        return $this->challenge instanceof PendingMfaChallenge;
    }
}
