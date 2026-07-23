<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MfaChallengeIntent;
use App\Modules\Identity\Domain\MfaStepUpPurpose;
use App\Modules\Identity\Domain\MfaVerificationMethod;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\Clock\ClockInterface;
use Throwable;
use ValueError;

/**
 * Issue and validate opaque MFA challenges inside the encrypted server session.
 */
final readonly class MfaChallengeStore
{
    /**
     * Create the challenge store with clock and public identifier adapters.
     */
    public function __construct(
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
    ) {}

    /**
     * Issue a session-bound login challenge for one active TOTP method.
     */
    public function issueLogin(
        Request $request,
        IdentityAccount $account,
        IdentityMfaMethod $method,
    ): PendingMfaChallenge {
        $ttlMinutes = (int) config('identity.mfa.challenge_ttl_minutes');
        $maximumAttempts = (int) config('identity.mfa.max_attempts');

        if ($ttlMinutes < 2 || $ttlMinutes > 15 || $maximumAttempts < 2 || $maximumAttempts > 10) {
            throw new LogicException('The MFA challenge security configuration is invalid.');
        }

        $now = CarbonImmutable::instance($this->clock->now());
        $methods = [MfaVerificationMethod::Totp];

        if ($method->recoveryCodes()->whereNull('used_at')->exists()) {
            $methods[] = MfaVerificationMethod::Recovery;
        }

        $challenge = new PendingMfaChallenge(
            id: $this->publicIds->generate()->toString(),
            identityAccountId: $account->id,
            intent: MfaChallengeIntent::Login,
            purpose: null,
            methods: $methods,
            expiresAt: $now->addMinutes($ttlMinutes),
            attemptsRemaining: $maximumAttempts,
            passwordAuthenticatedAt: $now,
        );
        $request->session()->put(
            BrowserSession::MFA_CHALLENGE,
            $this->serialize($challenge),
        );

        return $challenge;
    }

    /**
     * Issue a purpose-bound step-up challenge for an authenticated identity.
     */
    public function issueStepUp(
        Request $request,
        IdentityAccount $account,
        IdentityMfaMethod $method,
        MfaStepUpPurpose $purpose,
        CarbonImmutable $passwordAuthenticatedAt,
    ): PendingMfaChallenge {
        $ttlMinutes = (int) config('identity.mfa.challenge_ttl_minutes');
        $maximumAttempts = (int) config('identity.mfa.max_attempts');

        if ($ttlMinutes < 2 || $ttlMinutes > 15 || $maximumAttempts < 2 || $maximumAttempts > 10) {
            throw new LogicException('The MFA challenge security configuration is invalid.');
        }

        $now = CarbonImmutable::instance($this->clock->now());
        $methods = [MfaVerificationMethod::Totp];

        if ($method->recoveryCodes()->whereNull('used_at')->exists()) {
            $methods[] = MfaVerificationMethod::Recovery;
        }

        $challenge = new PendingMfaChallenge(
            id: $this->publicIds->generate()->toString(),
            identityAccountId: $account->id,
            intent: MfaChallengeIntent::StepUp,
            purpose: $purpose->value,
            methods: $methods,
            expiresAt: $now->addMinutes($ttlMinutes),
            attemptsRemaining: $maximumAttempts,
            passwordAuthenticatedAt: $passwordAuthenticatedAt,
        );
        $request->session()->put(
            BrowserSession::MFA_CHALLENGE,
            $this->serialize($challenge),
        );

        return $challenge;
    }

    /**
     * Resolve a valid challenge only from the current browser session.
     */
    public function resolve(Request $request, string $challengeId): ?PendingMfaChallenge
    {
        $state = $request->session()->get(BrowserSession::MFA_CHALLENGE);

        if (! is_array($state)) {
            return null;
        }

        try {
            $challenge = $this->hydrate($state);
        } catch (Throwable) {
            $request->session()->forget(BrowserSession::MFA_CHALLENGE);

            return null;
        }

        if (
            ! hash_equals($challenge->id, $challengeId)
            || ! $challenge->expiresAt->isAfter(CarbonImmutable::instance($this->clock->now()))
            || $challenge->attemptsRemaining < 1
        ) {
            $request->session()->forget(BrowserSession::MFA_CHALLENGE);

            return null;
        }

        return $challenge;
    }

    /**
     * Decrement a failed challenge and remove it when no attempts remain.
     */
    public function recordFailure(Request $request, PendingMfaChallenge $challenge): void
    {
        $remaining = $challenge->attemptsRemaining - 1;

        if ($remaining < 1) {
            $request->session()->forget(BrowserSession::MFA_CHALLENGE);

            return;
        }

        $request->session()->put(BrowserSession::MFA_CHALLENGE, $this->serialize(
            new PendingMfaChallenge(
                id: $challenge->id,
                identityAccountId: $challenge->identityAccountId,
                intent: $challenge->intent,
                purpose: $challenge->purpose,
                methods: $challenge->methods,
                expiresAt: $challenge->expiresAt,
                attemptsRemaining: $remaining,
                passwordAuthenticatedAt: $challenge->passwordAuthenticatedAt,
            ),
        ));
    }

    /**
     * Remove a challenge after successful verification.
     */
    public function consume(Request $request): void
    {
        $request->session()->forget(BrowserSession::MFA_CHALLENGE);
    }

    /**
     * Serialize a challenge into primitive session values.
     *
     * @return array{
     *     id: string,
     *     identity_account_id: int,
     *     intent: string,
     *     purpose: string|null,
     *     methods: list<string>,
     *     expires_at: string,
     *     attempts_remaining: int,
     *     password_authenticated_at: string
     * }
     */
    private function serialize(PendingMfaChallenge $challenge): array
    {
        return [
            'id' => $challenge->id,
            'identity_account_id' => $challenge->identityAccountId,
            'intent' => $challenge->intent->value,
            'purpose' => $challenge->purpose,
            'methods' => array_map(
                static fn (MfaVerificationMethod $method): string => $method->value,
                $challenge->methods,
            ),
            'expires_at' => $challenge->expiresAt->format(DATE_ATOM),
            'attempts_remaining' => $challenge->attemptsRemaining,
            'password_authenticated_at' => $challenge->passwordAuthenticatedAt->format(DATE_ATOM),
        ];
    }

    /**
     * Hydrate and validate primitive session state without trusting its shape.
     *
     * @param  array<mixed>  $state
     */
    private function hydrate(array $state): PendingMfaChallenge
    {
        $id = $state['id'] ?? null;
        $identityAccountId = $state['identity_account_id'] ?? null;
        $intent = $state['intent'] ?? null;
        $purpose = $state['purpose'] ?? null;
        $rawMethods = $state['methods'] ?? null;
        $expiresAt = $state['expires_at'] ?? null;
        $attemptsRemaining = $state['attempts_remaining'] ?? null;
        $passwordAuthenticatedAt = $state['password_authenticated_at'] ?? null;

        if (
            ! is_string($id)
            || ! is_int($identityAccountId)
            || ! is_string($intent)
            || ($purpose !== null && ! is_string($purpose))
            || ! is_array($rawMethods)
            || ! is_string($expiresAt)
            || ! is_int($attemptsRemaining)
            || ! is_string($passwordAuthenticatedAt)
        ) {
            throw new LogicException('The MFA challenge session state is malformed.');
        }

        $methods = [];

        foreach ($rawMethods as $rawMethod) {
            if (! is_string($rawMethod)) {
                throw new LogicException('The MFA challenge method state is malformed.');
            }

            try {
                $methods[] = MfaVerificationMethod::from($rawMethod);
            } catch (ValueError) {
                throw new LogicException('The MFA challenge method is unsupported.');
            }
        }

        if ($methods === []) {
            throw new LogicException('The MFA challenge must contain an authentication method.');
        }

        return new PendingMfaChallenge(
            id: $id,
            identityAccountId: $identityAccountId,
            intent: MfaChallengeIntent::from($intent),
            purpose: $purpose,
            methods: $methods,
            expiresAt: CarbonImmutable::parse($expiresAt),
            attemptsRemaining: $attemptsRemaining,
            passwordAuthenticatedAt: CarbonImmutable::parse($passwordAuthenticatedAt),
        );
    }
}
