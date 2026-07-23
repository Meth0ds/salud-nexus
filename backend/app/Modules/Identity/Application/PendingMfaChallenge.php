<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MfaChallengeIntent;
use App\Modules\Identity\Domain\MfaVerificationMethod;
use Carbon\CarbonImmutable;

/**
 * Carry a validated session-bound MFA challenge without exposing account identity.
 */
final readonly class PendingMfaChallenge
{
    /**
     * Create an immutable challenge state reconstructed from the server session.
     *
     * @param  list<MfaVerificationMethod>  $methods
     */
    public function __construct(
        public string $id,
        public int $identityAccountId,
        public MfaChallengeIntent $intent,
        public ?string $purpose,
        public array $methods,
        public CarbonImmutable $expiresAt,
        public int $attemptsRemaining,
        public CarbonImmutable $passwordAuthenticatedAt,
    ) {}

    /**
     * Return only the challenge fields safe for an unauthenticated browser.
     *
     * @return array{
     *     challenge_id: string,
     *     intent: string,
     *     purpose: string|null,
     *     methods: list<string>,
     *     expires_at: string,
     *     attempts_remaining: int
     * }
     */
    public function publicData(): array
    {
        return [
            'challenge_id' => $this->id,
            'intent' => $this->intent->value,
            'purpose' => $this->purpose,
            'methods' => array_map(
                static fn (MfaVerificationMethod $method): string => $method->value,
                $this->methods,
            ),
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
            'attempts_remaining' => $this->attemptsRemaining,
        ];
    }
}
