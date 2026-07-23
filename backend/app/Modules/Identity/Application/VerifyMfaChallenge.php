<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Domain\IdentityAccountStatus;
use App\Modules\Identity\Domain\IdentitySecurityEventType;
use App\Modules\Identity\Domain\MfaChallengeIntent;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Domain\MfaVerificationMethod;
use App\Modules\Identity\Domain\SecurityEventOutcome;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use LogicException;
use SensitiveParameter;
use Symfony\Component\Clock\ClockInterface;

/**
 * Verify a session-bound MFA challenge and establish an AAL2 browser session.
 */
final readonly class VerifyMfaChallenge
{
    /**
     * Create the verifier with factor, session, audit, and clock adapters.
     */
    public function __construct(
        private AuthFactory $auth,
        private MfaChallengeStore $challenges,
        private TotpAuthenticator $totp,
        private RecoveryCodeManager $recoveryCodes,
        private IdentitySecurityEventWriter $securityEvents,
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
    ) {}

    /**
     * Verify one factor and authenticate the active account represented by the challenge.
     */
    public function handle(
        Request $request,
        string $challengeId,
        MfaVerificationMethod $verificationMethod,
        #[SensitiveParameter] string $code,
    ): void {
        $challenge = $this->challenges->resolve($request, $challengeId);

        if (! $challenge instanceof PendingMfaChallenge) {
            throw new AuthenticationException(guards: ['web']);
        }

        if (! in_array($verificationMethod, $challenge->methods, true)) {
            $this->rejectForIntent($challenge);
        }

        $account = IdentityAccount::query()
            ->whereKey($challenge->identityAccountId)
            ->where('status', IdentityAccountStatus::Active->value)
            ->first();
        $method = IdentityMfaMethod::query()
            ->where('identity_account_id', $challenge->identityAccountId)
            ->where('type', MfaMethodType::Totp->value)
            ->where('status', MfaMethodStatus::Active->value)
            ->first();

        if (! $account instanceof IdentityAccount || ! $method instanceof IdentityMfaMethod) {
            $this->deny($request, $challenge, null, $verificationMethod);
        }

        $guard = $this->auth->guard('web');

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('MFA verification requires a stateful guard.');
        }

        $bindingIsValid = match ($challenge->intent) {
            MfaChallengeIntent::Login => $guard->guest(),
            MfaChallengeIntent::StepUp => $guard->check()
                && (string) $guard->id() === (string) $account->id,
        };

        if (! $bindingIsValid) {
            $this->challenges->consume($request);

            throw new AuthenticationException(guards: ['web']);
        }

        $verified = match ($verificationMethod) {
            MfaVerificationMethod::Totp => $this->totp->verifyAndAdvance($method, $code),
            MfaVerificationMethod::Recovery => $this->recoveryCodes->consume($method, $code),
        };

        if (! $verified) {
            $this->deny($request, $challenge, $account, $verificationMethod);
        }

        if ($challenge->intent === MfaChallengeIntent::Login) {
            $guard->login($account, false);
        } else {
            $request->session()->regenerate(true);
        }

        $this->challenges->consume($request);
        $request->session()->put([
            BrowserSession::METHOD => 'password+'.$verificationMethod->value,
            BrowserSession::LEVEL => 2,
            BrowserSession::AUTHENTICATED_AT => $this->clock->now()->format(DATE_ATOM),
            BrowserSession::PASSWORD_AUTHENTICATED_AT => $challenge
                ->passwordAuthenticatedAt
                ->format(DATE_ATOM),
            BrowserSession::PUBLIC_ID => $this->publicIds->generate()->toString(),
            BrowserSession::ASSURANCE_SCOPE => $challenge->intent === MfaChallengeIntent::Login
                ? 'all'
                : $challenge->purpose,
        ]);
        $this->securityEvents->write(
            account: $account,
            type: $challenge->intent === MfaChallengeIntent::Login
                ? IdentitySecurityEventType::MfaChallengeSucceeded
                : IdentitySecurityEventType::MfaStepUpSucceeded,
            outcome: SecurityEventOutcome::Succeeded,
            authenticationLevel: 2,
            requestPublicId: $this->requestPublicId($request),
            metadata: [
                'factor' => $verificationMethod->value,
                'intent' => $challenge->intent->value,
                'purpose' => $challenge->purpose,
            ],
        );

        if ($verificationMethod === MfaVerificationMethod::Recovery) {
            $this->securityEvents->write(
                account: $account,
                type: IdentitySecurityEventType::MfaRecoveryConsumed,
                outcome: SecurityEventOutcome::Succeeded,
                authenticationLevel: 2,
                requestPublicId: $this->requestPublicId($request),
                metadata: [
                    'factor' => $verificationMethod->value,
                    'recovery_codes_remaining' => $method
                        ->recoveryCodes()
                        ->whereNull('used_at')
                        ->count(),
                ],
            );
        }
    }

    /**
     * Record a uniform failed attempt and stop authentication.
     */
    private function deny(
        Request $request,
        PendingMfaChallenge $challenge,
        ?IdentityAccount $account,
        MfaVerificationMethod $verificationMethod,
    ): never {
        $this->challenges->recordFailure($request, $challenge);
        $this->securityEvents->write(
            account: $account,
            type: $challenge->intent === MfaChallengeIntent::Login
                ? IdentitySecurityEventType::MfaChallengeFailed
                : IdentitySecurityEventType::MfaStepUpFailed,
            outcome: SecurityEventOutcome::Denied,
            authenticationLevel: 1,
            requestPublicId: $this->requestPublicId($request),
            metadata: [
                'attempts_remaining' => max(0, $challenge->attemptsRemaining - 1),
                'factor' => $verificationMethod->value,
                'intent' => $challenge->intent->value,
                'purpose' => $challenge->purpose,
                'reason' => 'invalid_or_expired',
            ],
        );

        $this->rejectForIntent($challenge);
    }

    /**
     * Throw the public failure appropriate for the challenge's authentication state.
     */
    private function rejectForIntent(PendingMfaChallenge $challenge): never
    {
        if ($challenge->intent === MfaChallengeIntent::StepUp) {
            throw new AuthorizationException;
        }

        throw new AuthenticationException(guards: ['web']);
    }

    /**
     * Return the request identifier assigned before factor verification.
     */
    private function requestPublicId(Request $request): string
    {
        $requestPublicId = $request->attributes->get(AssignRequestId::ATTRIBUTE);

        if (! is_string($requestPublicId)) {
            throw new LogicException('MFA verification requires a request identifier.');
        }

        return $requestPublicId;
    }
}
