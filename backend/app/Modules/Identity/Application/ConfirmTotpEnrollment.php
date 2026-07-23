<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Confirm a pending TOTP method and disclose its recovery set exactly once.
 */
final readonly class ConfirmTotpEnrollment
{
    /**
     * Create the confirmation action with all cryptographic and session adapters.
     */
    public function __construct(
        private TotpAuthenticator $authenticator,
        private RecoveryCodeManager $recoveryCodes,
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
    ) {}

    /**
     * Activate the TOTP method, issue recovery codes, and elevate the session.
     *
     * @return list<string>
     */
    public function handle(
        Request $request,
        IdentityAccount $account,
        #[SensitiveParameter] string $code,
    ): array {
        $recoveryCodes = DB::transaction(function () use ($account, $code): array {
            $method = IdentityMfaMethod::query()
                ->where('identity_account_id', $account->id)
                ->where('type', MfaMethodType::Totp->value)
                ->lockForUpdate()
                ->first();
            $now = CarbonImmutable::instance($this->clock->now());

            if (
                ! $method instanceof IdentityMfaMethod
                || $method->status !== MfaMethodStatus::Pending
                || $method->secret_revealed_at === null
                || $method->enrollment_expires_at === null
                || ! $method->enrollment_expires_at->isAfter($now)
            ) {
                throw new ConflictHttpException;
            }

            if (! $this->authenticator->verifyAndAdvance($method, $code)) {
                throw ValidationException::withMessages([
                    'code' => 'The authentication code is invalid or expired.',
                ]);
            }

            $method->status = MfaMethodStatus::Active;
            $method->confirmed_at = $now;
            $method->enrollment_expires_at = null;
            $method->save();

            return $this->recoveryCodes->generateFor($method);
        }, 3);

        $request->session()->regenerate(true);
        $request->session()->put([
            BrowserSession::METHOD => 'password+totp',
            BrowserSession::LEVEL => 2,
            BrowserSession::AUTHENTICATED_AT => $this->clock->now()->format(DATE_ATOM),
            BrowserSession::PUBLIC_ID => $this->publicIds->generate()->toString(),
        ]);

        return $recoveryCodes;
    }
}
