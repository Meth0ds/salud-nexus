<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Domain\IdentityAccountStatus;
use App\Modules\Identity\Domain\IdentitySecurityEventType;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Domain\MfaStepUpPurpose;
use App\Modules\Identity\Domain\SecurityEventOutcome;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Issue a purpose-bound MFA challenge for an authenticated AAL1 session.
 */
final readonly class BeginMfaStepUp
{
    /**
     * Create the step-up action with session, event, and clock adapters.
     */
    public function __construct(
        private MfaChallengeStore $challenges,
        private IdentitySecurityEventWriter $securityEvents,
        private ClockInterface $clock,
    ) {}

    /**
     * Rotate the session and issue a challenge for an allowlisted purpose.
     */
    public function handle(
        Request $request,
        MfaStepUpPurpose $purpose,
    ): PendingMfaChallenge {
        $account = $request->user('web');
        $level = $request->session()->get(BrowserSession::LEVEL);
        $passwordAuthenticatedAt = $request->session()->get(
            BrowserSession::PASSWORD_AUTHENTICATED_AT,
        );

        if (
            ! $account instanceof IdentityAccount
            || $account->status !== IdentityAccountStatus::Active
            || ! is_int($level)
            || $level < 1
            || ! is_string($passwordAuthenticatedAt)
        ) {
            throw new AuthenticationException(guards: ['web']);
        }

        try {
            $passwordProof = CarbonImmutable::parse($passwordAuthenticatedAt);
        } catch (Throwable) {
            throw new AuthenticationException(guards: ['web']);
        }

        if ($passwordProof->isAfter(CarbonImmutable::instance($this->clock->now())->addMinute())) {
            throw new AuthenticationException(guards: ['web']);
        }

        $method = IdentityMfaMethod::query()
            ->where('identity_account_id', $account->id)
            ->where('type', MfaMethodType::Totp->value)
            ->where('status', MfaMethodStatus::Active->value)
            ->first();

        if (! $method instanceof IdentityMfaMethod) {
            throw new ConflictHttpException;
        }

        $requestPublicId = $request->attributes->get(AssignRequestId::ATTRIBUTE);

        if (! is_string($requestPublicId)) {
            throw new LogicException('MFA step-up requires a request identifier.');
        }

        $request->session()->regenerate(true);
        $challenge = $this->challenges->issueStepUp(
            $request,
            $account,
            $method,
            $purpose,
            $passwordProof,
        );
        $this->securityEvents->write(
            account: $account,
            type: IdentitySecurityEventType::MfaStepUpIssued,
            outcome: SecurityEventOutcome::Succeeded,
            authenticationLevel: $level,
            requestPublicId: $requestPublicId,
            metadata: [
                'attempts_remaining' => $challenge->attemptsRemaining,
                'factor' => 'totp',
                'intent' => $challenge->intent->value,
                'purpose' => $purpose->value,
            ],
        );

        return $challenge;
    }
}
