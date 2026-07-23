<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Application\RecoveryCodeManager;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verify high-entropy recovery code issuance, storage, rotation, and consumption.
 */
final class RecoveryCodeManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_codes_are_unique_formatted_and_never_stored_in_plaintext(): void
    {
        $method = $this->activeMethod();
        $manager = $this->app->make(RecoveryCodeManager::class);
        $codes = $manager->generateFor($method);

        self::assertCount(10, $codes);
        self::assertCount(10, array_unique($codes));

        foreach ($codes as $code) {
            self::assertMatchesRegularExpression(
                '/^(?:[2-9A-HJKMNP-TV-Z]{6}-){3}[2-9A-HJKMNP-TV-Z]{6}$/D',
                $code,
            );
        }

        $records = DB::table('identity_recovery_codes')
            ->where('identity_mfa_method_id', $method->id)
            ->get(['lookup_digest', 'code_hash']);

        self::assertCount(10, $records);

        foreach ($records as $record) {
            self::assertIsString($record->lookup_digest);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $record->lookup_digest);
            self::assertIsString($record->code_hash);
            self::assertStringStartsWith('$argon2id$', $record->code_hash);

            foreach ($codes as $code) {
                self::assertStringNotContainsString(
                    str_replace('-', '', $code),
                    $record->lookup_digest.$record->code_hash,
                );
            }
        }
    }

    public function test_a_recovery_code_is_account_bound_and_can_be_consumed_only_once(): void
    {
        $method = $this->activeMethod();
        $otherMethod = $this->activeMethod();
        $manager = $this->app->make(RecoveryCodeManager::class);
        $code = $manager->generateFor($method)[0];
        $normalizedInput = strtolower(str_replace('-', ' ', $code));

        self::assertFalse($manager->consume($otherMethod, $normalizedInput));
        self::assertTrue($manager->consume($method, $normalizedInput));
        self::assertFalse($manager->consume($method, $normalizedInput));
        self::assertSame(
            1,
            DB::table('identity_recovery_codes')
                ->where('identity_mfa_method_id', $method->id)
                ->whereNotNull('used_at')
                ->count(),
        );
    }

    public function test_rotating_codes_invalidates_the_previous_set(): void
    {
        $method = $this->activeMethod();
        $manager = $this->app->make(RecoveryCodeManager::class);
        $oldCode = $manager->generateFor($method)[0];
        $newCodes = $manager->generateFor($method);

        self::assertFalse($manager->consume($method, $oldCode));
        self::assertTrue($manager->consume($method, $newCodes[0]));
        self::assertSame(
            10,
            DB::table('identity_recovery_codes')
                ->where('identity_mfa_method_id', $method->id)
                ->count(),
        );
    }

    /**
     * Persist an active TOTP method that can own recovery codes.
     */
    private function activeMethod(): IdentityMfaMethod
    {
        $method = new IdentityMfaMethod;
        $method->identity_account_id = IdentityAccount::factory()->create()->id;
        $method->type = MfaMethodType::Totp;
        $method->status = MfaMethodStatus::Active;
        $method->secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $method->confirmed_at = now()->toImmutable();
        $method->save();

        return $method;
    }
}
