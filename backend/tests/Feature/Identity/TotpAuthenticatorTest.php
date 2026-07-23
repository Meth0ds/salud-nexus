<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Application\TotpAuthenticator;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Tests\TestCase;

/**
 * Verify deterministic TOTP generation, validation, and replay protection.
 */
final class TotpAuthenticatorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            ClockInterface::class,
            new MockClock('2026-07-23T12:00:00+00:00'),
        );
    }

    public function test_it_generates_a_high_entropy_secret_and_canonical_provisioning_uri(): void
    {
        $account = IdentityAccount::factory()->create([
            'email' => 'patient@example.test',
        ]);
        $secret = $this->authenticator()->generateSecret();
        $uri = $this->authenticator()->provisioningUri($account, $secret);

        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/D', $secret);
        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('patient%40example.test', $uri);
        self::assertStringContainsString('secret='.$secret, $uri);
        self::assertStringContainsString('algorithm=SHA1', $uri);
        self::assertStringContainsString('digits=6', $uri);
        self::assertStringContainsString('period=30', $uri);
    }

    public function test_google2fa_engine_matches_the_rfc_6238_sha1_reference_vector(): void
    {
        $engine = new Google2FA;
        $engine->setAlgorithm('sha1');
        $engine->setOneTimePasswordLength(8);
        $rfcSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        self::assertSame('94287082', $engine->oathTotp($rfcSecret, intdiv(59, 30)));
    }

    public function test_a_valid_totp_advances_the_method_and_cannot_be_replayed(): void
    {
        $method = $this->createMethod();
        $step = $this->currentStep();
        $code = $this->codeForStep($step);

        self::assertTrue($this->authenticator()->verifyAndAdvance($method, $code));
        self::assertSame($step, $this->lastUsedStep($method));
        self::assertFalse($this->authenticator()->verifyAndAdvance($method, $code));

        $this->clock()->sleep(30);
        $nextStep = $this->currentStep();

        self::assertTrue(
            $this->authenticator()->verifyAndAdvance($method, $this->codeForStep($nextStep)),
        );
        self::assertSame($nextStep, $this->lastUsedStep($method));
    }

    public function test_the_configured_clock_window_accepts_an_unused_adjacent_step_only_once(): void
    {
        $method = $this->createMethod();
        $currentStep = $this->currentStep();
        $previousCode = $this->codeForStep($currentStep - 1);

        self::assertTrue($this->authenticator()->verifyAndAdvance($method, $previousCode));
        self::assertSame($currentStep - 1, $this->lastUsedStep($method));
        self::assertFalse($this->authenticator()->verifyAndAdvance($method, $previousCode));
        self::assertTrue(
            $this->authenticator()->verifyAndAdvance($method, $this->codeForStep($currentStep)),
        );
    }

    public function test_malformed_codes_and_disabled_or_expired_methods_are_rejected(): void
    {
        $method = $this->createMethod();

        self::assertFalse($this->authenticator()->verifyAndAdvance($method, '12345'));
        self::assertNull($this->lastUsedStep($method));

        $method->status = MfaMethodStatus::Disabled;
        $method->disabled_at = CarbonImmutable::instance($this->clock()->now());
        $method->save();

        self::assertFalse(
            $this->authenticator()->verifyAndAdvance($method, $this->codeForStep($this->currentStep())),
        );

        $expired = $this->createMethod(
            account: IdentityAccount::factory()->create(),
            status: MfaMethodStatus::Pending,
            enrollmentExpiresAt: CarbonImmutable::instance(
                $this->clock()->now()->modify('-1 second'),
            ),
        );

        self::assertFalse(
            $this->authenticator()->verifyAndAdvance($expired, $this->codeForStep($this->currentStep())),
        );
    }

    /**
     * Persist a TOTP method in the requested lifecycle state.
     */
    private function createMethod(
        ?IdentityAccount $account = null,
        MfaMethodStatus $status = MfaMethodStatus::Active,
        ?CarbonImmutable $enrollmentExpiresAt = null,
    ): IdentityMfaMethod {
        $method = new IdentityMfaMethod;
        $method->identity_account_id = ($account ?? IdentityAccount::factory()->create())->id;
        $method->type = MfaMethodType::Totp;
        $method->status = $status;
        $method->secret = self::SECRET;
        $method->enrollment_expires_at = $enrollmentExpiresAt;
        $method->confirmed_at = $status === MfaMethodStatus::Active
            ? CarbonImmutable::instance($this->clock()->now())
            : null;
        $method->save();

        return $method;
    }

    /**
     * Calculate the current RFC 6238 time step from the injected clock.
     */
    private function currentStep(): int
    {
        return intdiv($this->clock()->now()->getTimestamp(), 30);
    }

    /**
     * Generate a deterministic six-digit code for the requested time step.
     */
    private function codeForStep(int $step): string
    {
        return (new Google2FA)->oathTotp(self::SECRET, $step);
    }

    /**
     * Refresh a method and return its persisted replay-prevention step.
     */
    private function lastUsedStep(IdentityMfaMethod $method): ?int
    {
        $method->refresh();

        return $method->last_used_step;
    }

    /**
     * Resolve the deterministic clock registered for this test.
     */
    private function clock(): MockClock
    {
        $clock = $this->app->make(ClockInterface::class);
        self::assertInstanceOf(MockClock::class, $clock);

        return $clock;
    }

    /**
     * Resolve a fresh authenticator using the deterministic test clock.
     */
    private function authenticator(): TotpAuthenticator
    {
        return $this->app->make(TotpAuthenticator::class);
    }
}
