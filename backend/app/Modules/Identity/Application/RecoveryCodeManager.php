<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use App\Modules\Identity\Infrastructure\Persistence\IdentityRecoveryCode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;
use Symfony\Component\Clock\ClockInterface;

/**
 * Issue and consume high-entropy one-use MFA recovery codes.
 */
final readonly class RecoveryCodeManager
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * Create the recovery code manager with hardened hashing and clock adapters.
     */
    public function __construct(
        private Hasher $hasher,
        private ClockInterface $clock,
    ) {}

    /**
     * Replace the method's recovery set and return plaintext codes exactly once.
     *
     * @return list<string>
     */
    public function generateFor(IdentityMfaMethod $method): array
    {
        $method->refresh();

        if ($method->status !== MfaMethodStatus::Active) {
            throw new InvalidArgumentException('Recovery codes require an active TOTP method.');
        }

        $count = (int) config('identity.mfa.recovery_code_count');
        $length = (int) config('identity.mfa.recovery_code_length');

        if ($count < 8 || $count > 20 || $length < 23 || $length > 32) {
            throw new LogicException('The recovery code security configuration is invalid.');
        }

        $codes = [];
        $records = [];

        while (count($codes) < $count) {
            $code = $this->generateCode($length);

            if (isset($records[$code])) {
                continue;
            }

            $codes[] = $code;
            $records[$code] = [
                'lookup_digest' => $this->lookupDigest($code),
                'code_hash' => $this->hasher->make($this->normalize($code)),
            ];
        }

        DB::transaction(function () use ($method, $records): void {
            IdentityRecoveryCode::query()
                ->where('identity_mfa_method_id', $method->id)
                ->delete();

            foreach ($records as $record) {
                $recoveryCode = new IdentityRecoveryCode;
                $recoveryCode->identity_mfa_method_id = $method->id;
                $recoveryCode->lookup_digest = $record['lookup_digest'];
                $recoveryCode->code_hash = $record['code_hash'];
                $recoveryCode->save();
            }
        }, 3);

        return $codes;
    }

    /**
     * Atomically consume a valid recovery code owned by the supplied method.
     */
    public function consume(
        IdentityMfaMethod $method,
        #[SensitiveParameter] string $code,
    ): bool {
        $normalized = $this->normalize($code);
        $length = (int) config('identity.mfa.recovery_code_length');

        if (
            $method->status !== MfaMethodStatus::Active
            || strlen($normalized) !== $length
            || strspn($normalized, self::ALPHABET) !== $length
        ) {
            return false;
        }

        $lookupDigest = $this->lookupDigest($normalized);

        return DB::transaction(function () use ($method, $normalized, $lookupDigest): bool {
            $recoveryCode = IdentityRecoveryCode::query()
                ->where('identity_mfa_method_id', $method->id)
                ->where('lookup_digest', $lookupDigest)
                ->lockForUpdate()
                ->first();

            if (
                ! $recoveryCode instanceof IdentityRecoveryCode
                || $recoveryCode->used_at !== null
                || ! $this->hasher->check($normalized, $recoveryCode->code_hash)
            ) {
                return false;
            }

            $recoveryCode->used_at = CarbonImmutable::instance($this->clock->now());
            $recoveryCode->save();

            return true;
        }, 3);
    }

    /**
     * Generate an unbiased code and format it in readable six-character groups.
     */
    private function generateCode(int $length): string
    {
        $rawCode = '';
        $maximumIndex = strlen(self::ALPHABET) - 1;

        for ($index = 0; $index < $length; $index++) {
            $rawCode .= self::ALPHABET[random_int(0, $maximumIndex)];
        }

        return implode('-', str_split($rawCode, 6));
    }

    /**
     * Normalize human-friendly separators without accepting other characters.
     */
    private function normalize(#[SensitiveParameter] string $code): string
    {
        $normalized = preg_replace('/[\s-]+/u', '', strtoupper(trim($code)));

        return is_string($normalized) ? $normalized : '';
    }

    /**
     * Derive a keyed lookup digest that remains resistant to database disclosure.
     */
    private function lookupDigest(#[SensitiveParameter] string $code): string
    {
        return hash_hmac('sha256', $this->normalize($code), $this->lookupKey());
    }

    /**
     * Derive a purpose-separated lookup key from protected application key material.
     */
    private function lookupKey(): string
    {
        $configuredKey = (string) config('identity.mfa.recovery_lookup_key');
        $keyMaterial = str_starts_with($configuredKey, 'base64:')
            ? base64_decode(substr($configuredKey, 7), true)
            : $configuredKey;

        if (! is_string($keyMaterial) || strlen($keyMaterial) < 32) {
            throw new LogicException('The recovery code lookup key must contain at least 256 bits.');
        }

        return hash_hkdf(
            'sha256',
            $keyMaterial,
            32,
            'identity-recovery-code-lookup-v1',
        );
    }
}
