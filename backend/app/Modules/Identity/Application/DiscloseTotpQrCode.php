<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Disclose a pending TOTP provisioning QR code exactly once.
 */
final readonly class DiscloseTotpQrCode
{
    /**
     * Create the disclosure action with provisioning, rendering, and clock adapters.
     */
    public function __construct(
        private TotpAuthenticator $authenticator,
        private TotpQrCodeRenderer $renderer,
        private ClockInterface $clock,
    ) {}

    /**
     * Render and consume the pending QR disclosure for the owning identity.
     */
    public function handle(IdentityAccount $account): string
    {
        return DB::transaction(function () use ($account): string {
            $method = IdentityMfaMethod::query()
                ->where('identity_account_id', $account->id)
                ->where('type', MfaMethodType::Totp->value)
                ->lockForUpdate()
                ->first();
            $now = CarbonImmutable::instance($this->clock->now());

            if (
                ! $method instanceof IdentityMfaMethod
                || $method->status !== MfaMethodStatus::Pending
                || $method->enrollment_expires_at === null
                || ! $method->enrollment_expires_at->isAfter($now)
                || $method->secret_revealed_at !== null
            ) {
                throw new ConflictHttpException;
            }

            $svg = $this->renderer->render(
                $this->authenticator->provisioningUri($account, $method->secret),
            );
            $method->secret_revealed_at = $now;
            $method->save();

            return $svg;
        }, 3);
    }
}
