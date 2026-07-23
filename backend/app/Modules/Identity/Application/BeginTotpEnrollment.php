<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\IdentitySecurityEventType;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Domain\SecurityEventOutcome;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Create or safely rotate a short-lived pending TOTP enrollment.
 */
final readonly class BeginTotpEnrollment
{
    /**
     * Create the enrollment action with secret generation and clock adapters.
     */
    public function __construct(
        private TotpAuthenticator $authenticator,
        private ClockInterface $clock,
        private IdentitySecurityEventWriter $securityEvents,
    ) {}

    /**
     * Start a new pending TOTP enrollment for the authenticated identity.
     */
    public function handle(
        IdentityAccount $account,
        string $requestPublicId,
    ): IdentityMfaMethod {
        $ttlMinutes = (int) config('identity.mfa.enrollment_ttl_minutes');

        if ($ttlMinutes < 2 || $ttlMinutes > 15) {
            throw new LogicException('The MFA enrollment lifetime is invalid.');
        }

        return DB::transaction(function () use (
            $account,
            $ttlMinutes,
            $requestPublicId,
        ): IdentityMfaMethod {
            $method = IdentityMfaMethod::query()
                ->where('identity_account_id', $account->id)
                ->where('type', MfaMethodType::Totp->value)
                ->lockForUpdate()
                ->first();

            if ($method instanceof IdentityMfaMethod && $method->status === MfaMethodStatus::Active) {
                throw new ConflictHttpException;
            }

            $method ??= new IdentityMfaMethod;
            $method->identity_account_id = $account->id;
            $method->type = MfaMethodType::Totp;
            $method->status = MfaMethodStatus::Pending;
            $method->secret = $this->authenticator->generateSecret();
            $method->last_used_step = null;
            $method->enrollment_expires_at = CarbonImmutable::instance($this->clock->now())
                ->addMinutes($ttlMinutes);
            $method->secret_revealed_at = null;
            $method->confirmed_at = null;
            $method->disabled_at = null;
            $method->save();
            $method->recoveryCodes()->delete();
            $this->securityEvents->write(
                account: $account,
                type: IdentitySecurityEventType::MfaEnrollmentStarted,
                outcome: SecurityEventOutcome::Succeeded,
                authenticationLevel: 1,
                requestPublicId: $requestPublicId,
                metadata: [
                    'factor' => 'totp',
                    'intent' => 'enrollment',
                ],
            );

            return $method;
        }, 3);
    }
}
