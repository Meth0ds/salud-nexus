<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Application\BrowserSession;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Tests\TestCase;

/**
 * Verify purpose-bound MFA step-up and fail-closed AAL2 middleware.
 */
final class MfaStepUpTest extends TestCase
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

        Route::middleware([
            'web',
            'auth.assurance:2,5,patient_data_export',
        ])->get('/api/v1/testing/aal2-export', static fn () => response()->noContent());
    }

    public function test_an_aal1_session_can_step_up_for_an_allowlisted_purpose(): void
    {
        $account = $this->accountWithMfa();
        $this->authenticateAtAal1($account);
        $sessionIdBeforeChallenge = $this->app['session']->getId();

        $challenge = $this->postJson('/api/v1/auth/mfa/step-up-challenges', [
            'purpose' => 'patient_data_export',
        ]);

        $challenge
            ->assertCreated()
            ->assertJsonPath('data.intent', 'step_up')
            ->assertJsonPath('data.purpose', 'patient_data_export')
            ->assertJsonPath('data.methods.0', 'totp');
        $this->assertAuthenticatedAs($account, 'web');
        self::assertNotSame($sessionIdBeforeChallenge, $this->app['session']->getId());
        self::assertSame(1, $this->app['session']->get(BrowserSession::LEVEL));
        $this->getJson('/api/v1/testing/aal2-export')->assertForbidden();

        $challengeId = $challenge->json('data.challenge_id');
        self::assertIsString($challengeId);
        $sessionIdBeforeFactor = $this->app['session']->getId();

        $this->postJson('/api/v1/auth/mfa/challenge-verifications', [
            'challenge_id' => $challengeId,
            'method' => 'totp',
            'code' => $this->currentCode(),
        ])->assertNoContent();

        $this->assertAuthenticatedAs($account, 'web');
        self::assertNotSame($sessionIdBeforeFactor, $this->app['session']->getId());
        self::assertSame(2, $this->app['session']->get(BrowserSession::LEVEL));
        self::assertSame(
            'patient_data_export',
            $this->app['session']->get(BrowserSession::ASSURANCE_SCOPE),
        );
        $this->getJson('/api/v1/testing/aal2-export')->assertNoContent();
        $this->assertDatabaseHas('identity_security_events', [
            'identity_account_id' => $account->id,
            'event_type' => 'mfa.step_up.issued',
            'result' => 'succeeded',
        ]);
        $this->assertDatabaseHas('identity_security_events', [
            'identity_account_id' => $account->id,
            'event_type' => 'mfa.step_up.succeeded',
            'result' => 'succeeded',
        ]);
    }

    public function test_step_up_rejects_unknown_purposes_wrong_factors_and_account_mixing(): void
    {
        $account = $this->accountWithMfa();
        $this->authenticateAtAal1($account);

        $this->postJson('/api/v1/auth/mfa/step-up-challenges', [
            'purpose' => 'arbitrary_admin_action',
            'unexpected' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose', 'unexpected']);

        $challenge = $this->postJson('/api/v1/auth/mfa/step-up-challenges', [
            'purpose' => 'patient_data_export',
        ]);
        $challengeId = $challenge->json('data.challenge_id');
        self::assertIsString($challengeId);

        $this->postJson('/api/v1/auth/mfa/challenge-verifications', [
            'challenge_id' => $challengeId,
            'method' => 'totp',
            'code' => $this->differentCode($this->currentCode()),
        ])->assertForbidden();

        self::assertSame(1, $this->app['session']->get(BrowserSession::LEVEL));

        $otherAccount = $this->accountWithMfa();
        $this->authenticateAtAal1($otherAccount);

        $this->postJson('/api/v1/auth/mfa/challenge-verifications', [
            'challenge_id' => $challengeId,
            'method' => 'totp',
            'code' => $this->currentCode(),
        ])->assertUnauthorized();
        self::assertSame(1, $this->app['session']->get(BrowserSession::LEVEL));
    }

    public function test_assurance_middleware_denies_stale_missing_and_mismatched_scope(): void
    {
        $account = IdentityAccount::factory()->create();
        $now = $this->clock()->now();

        $this->actingAs($account, 'web')->withSession([
            ...$this->aal2Metadata($now->format(DATE_ATOM)),
            BrowserSession::ASSURANCE_SCOPE => 'clinical_document_download',
        ]);
        $this->getJson('/api/v1/testing/aal2-export')->assertForbidden();

        $this->withSession([
            ...$this->aal2Metadata($now->modify('-6 minutes')->format(DATE_ATOM)),
            BrowserSession::ASSURANCE_SCOPE => 'patient_data_export',
        ]);
        $this->getJson('/api/v1/testing/aal2-export')->assertForbidden();

        $this->withSession([
            ...$this->aal2Metadata($now->format(DATE_ATOM)),
            BrowserSession::ASSURANCE_SCOPE => 'all',
        ]);
        $this->getJson('/api/v1/testing/aal2-export')->assertNoContent();

        $this->withSession([
            BrowserSession::METHOD => 'password',
            BrowserSession::LEVEL => 1,
            BrowserSession::AUTHENTICATED_AT => $now->format(DATE_ATOM),
        ]);
        $this->getJson('/api/v1/testing/aal2-export')->assertForbidden();
    }

    /**
     * Authenticate an identity with fresh AAL1 session metadata.
     */
    private function authenticateAtAal1(IdentityAccount $account): void
    {
        $now = $this->clock()->now()->format(DATE_ATOM);
        $this->actingAs($account, 'web')->withSession([
            BrowserSession::METHOD => 'password',
            BrowserSession::LEVEL => 1,
            BrowserSession::AUTHENTICATED_AT => $now,
            BrowserSession::PASSWORD_AUTHENTICATED_AT => $now,
            BrowserSession::PUBLIC_ID => '01983a6f-c400-7b03-88bc-15cb550905a9',
        ]);
    }

    /**
     * Build complete AAL2 metadata for middleware scenarios.
     *
     * @return array<string, int|string>
     */
    private function aal2Metadata(string $authenticatedAt): array
    {
        return [
            BrowserSession::METHOD => 'password+totp',
            BrowserSession::LEVEL => 2,
            BrowserSession::AUTHENTICATED_AT => $authenticatedAt,
            BrowserSession::PASSWORD_AUTHENTICATED_AT => $authenticatedAt,
            BrowserSession::PUBLIC_ID => '01983a6f-c400-7b03-88bc-15cb550905a9',
        ];
    }

    /**
     * Create an active identity with one TOTP method.
     */
    private function accountWithMfa(): IdentityAccount
    {
        $account = IdentityAccount::factory()->create();
        $method = new IdentityMfaMethod;
        $method->identity_account_id = $account->id;
        $method->type = MfaMethodType::Totp;
        $method->status = MfaMethodStatus::Active;
        $method->secret = self::SECRET;
        $method->confirmed_at = CarbonImmutable::instance($this->clock()->now());
        $method->save();

        return $account;
    }

    /**
     * Generate the current deterministic six-digit TOTP.
     */
    private function currentCode(): string
    {
        $step = intdiv($this->clock()->now()->getTimestamp(), 30);

        return (new Google2FA)->oathTotp(self::SECRET, $step);
    }

    /**
     * Produce a well-formed code that differs from the valid code.
     */
    private function differentCode(string $validCode): string
    {
        $replacement = $validCode[0] === '9' ? '0' : (string) (((int) $validCode[0]) + 1);

        return $replacement.substr($validCode, 1);
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
}
