<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PragmaRX\Google2FA\Google2FA;
use SensitiveParameter;
use Symfony\Component\Clock\ClockInterface;

/**
 * Generate and atomically verify RFC 6238 TOTP authenticators.
 */
final class TotpAuthenticator
{
    private readonly int $digits;

    private readonly int $periodSeconds;

    private readonly int $window;

    private readonly int $secretLength;

    private readonly string $issuer;

    /**
     * Configure the maintained Google2FA engine from validated application settings.
     */
    public function __construct(
        private readonly Google2FA $engine,
        private readonly ClockInterface $clock,
    ) {
        $this->digits = (int) config('identity.mfa.totp.digits');
        $this->periodSeconds = (int) config('identity.mfa.totp.period_seconds');
        $this->window = (int) config('identity.mfa.totp.window');
        $this->secretLength = (int) config('identity.mfa.totp.secret_length');
        $this->issuer = trim((string) config('identity.mfa.totp.issuer'));

        if (
            $this->digits !== 6
            || $this->periodSeconds !== 30
            || $this->window < 0
            || $this->window > 1
            || $this->secretLength < 32
            || $this->secretLength > 64
            || $this->issuer === ''
        ) {
            throw new InvalidArgumentException('The TOTP security configuration is invalid.');
        }

        $this->engine->setAlgorithm('sha1');
        $this->engine->setOneTimePasswordLength($this->digits);
        $this->engine->setKeyRegeneration($this->periodSeconds);
        $this->engine->setWindow($this->window);
    }

    /**
     * Generate at least 160 bits of Base32 TOTP secret material.
     */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey($this->secretLength);
    }

    /**
     * Build the canonical authenticator provisioning URI for an identity.
     */
    public function provisioningUri(
        IdentityAccount $account,
        #[SensitiveParameter] string $secret,
    ): string {
        return $this->engine->getQRCodeUrl(
            $this->issuer,
            (string) $account->email,
            $secret,
        );
    }

    /**
     * Verify a code while atomically preventing reuse of an accepted time step.
     */
    public function verifyAndAdvance(
        IdentityMfaMethod $method,
        #[SensitiveParameter] string $code,
    ): bool {
        if (preg_match('/^\d{'.$this->digits.'}$/D', $code) !== 1) {
            return false;
        }

        return DB::transaction(function () use ($method, $code): bool {
            $lockedMethod = IdentityMfaMethod::query()
                ->whereKey($method->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedMethod instanceof IdentityMfaMethod || ! $this->isUsable($lockedMethod)) {
                return false;
            }

            $currentStep = intdiv($this->clock->now()->getTimestamp(), $this->periodSeconds);
            $previousStep = $lockedMethod->last_used_step ?? -1;
            $matchedStep = $this->engine->verifyKeyNewer(
                $lockedMethod->secret,
                $code,
                $previousStep,
                $this->window,
                $currentStep,
            );

            if ($matchedStep === false || ! is_int($matchedStep) || $matchedStep <= $previousStep) {
                return false;
            }

            $lockedMethod->last_used_step = $matchedStep;
            $lockedMethod->save();
            $method->last_used_step = $matchedStep;

            return true;
        }, 3);
    }

    /**
     * Determine whether a TOTP method may participate in a verification ceremony.
     */
    private function isUsable(IdentityMfaMethod $method): bool
    {
        if ($method->status === MfaMethodStatus::Disabled) {
            return false;
        }

        if ($method->status === MfaMethodStatus::Active) {
            return true;
        }

        return $method->enrollment_expires_at !== null
            && $method->enrollment_expires_at->getTimestamp() > $this->clock->now()->getTimestamp();
    }
}
