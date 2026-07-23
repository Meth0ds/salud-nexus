<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Enumerate minimized authentication events suitable for security monitoring.
 */
enum IdentitySecurityEventType: string
{
    case MfaEnrollmentStarted = 'mfa.enrollment.started';
    case MfaQrDisclosed = 'mfa.enrollment.qr_disclosed';
    case MfaEnrollmentConfirmed = 'mfa.enrollment.confirmed';
    case MfaChallengeIssued = 'mfa.challenge.issued';
    case MfaChallengeFailed = 'mfa.challenge.failed';
    case MfaChallengeSucceeded = 'mfa.challenge.succeeded';
    case MfaRecoveryConsumed = 'mfa.recovery.consumed';
    case MfaStepUpIssued = 'mfa.step_up.issued';
    case MfaStepUpSucceeded = 'mfa.step_up.succeeded';
    case MfaStepUpFailed = 'mfa.step_up.failed';
}
