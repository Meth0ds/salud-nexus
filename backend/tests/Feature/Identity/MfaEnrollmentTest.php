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
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use LogicException;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Verify the complete TOTP enrollment and one-time recovery disclosure ceremony.
 */
final class MfaEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            ClockInterface::class,
            new MockClock('2026-07-23T12:00:00+00:00'),
        );
    }

    public function test_a_recent_password_session_can_enroll_confirm_and_reach_aal2(): void
    {
        $account = IdentityAccount::factory()->create([
            'email' => 'patient@example.test',
        ]);
        $this->authenticateRecently($account);

        $enrollment = $this->postJson('/api/v1/auth/mfa/totp/enrollments');

        $enrollment
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.method', 'totp')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.qr_disclosure_required', true)
            ->assertJsonStructure(['data' => ['expires_at'], 'meta' => ['request_id']]);
        $this->assertResponseContainsNoSecrets($this->responseContent($enrollment));

        $method = IdentityMfaMethod::query()->where('identity_account_id', $account->id)->sole();
        $storedSecret = DB::table('identity_mfa_methods')
            ->where('id', $method->id)
            ->value('secret');
        self::assertIsString($storedSecret);
        self::assertNotSame($method->secret, $storedSecret);

        $qr = $this->postJson('/api/v1/auth/mfa/totp/enrollment-qr-disclosures');

        $qr
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private');
        $qrContent = $this->responseContent($qr);
        self::assertStringContainsString('<svg', $qrContent);
        self::assertStringNotContainsString($method->secret, $qrContent);
        self::assertStringNotContainsString((string) $account->email, $qrContent);

        $this->postJson('/api/v1/auth/mfa/totp/enrollment-qr-disclosures')
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json');

        $sessionIdBeforeConfirmation = $this->app['session']->getId();
        $code = $this->codeFor($method->secret);
        $confirmation = $this->postJson(
            '/api/v1/auth/mfa/totp/enrollment-confirmations',
            ['code' => $code],
        );

        $confirmation
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.method', 'totp')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(10, 'data.recovery_codes')
            ->assertJsonStructure(['meta' => ['request_id']]);
        $this->assertResponseContainsNoSecrets($this->responseContent($confirmation));

        $method->refresh();
        self::assertSame(MfaMethodStatus::Active, $method->status);
        self::assertNotNull($method->confirmed_at);
        self::assertNull($method->enrollment_expires_at);
        self::assertSame(10, $method->recoveryCodes()->count());
        self::assertSame(
            [
                'mfa.enrollment.started',
                'mfa.enrollment.qr_disclosed',
                'mfa.enrollment.confirmed',
            ],
            DB::table('identity_security_events')
                ->where('identity_account_id', $account->id)
                ->orderBy('id')
                ->pluck('event_type')
                ->all(),
        );
        $securityMetadata = DB::table('identity_security_events')
            ->where('identity_account_id', $account->id)
            ->pluck('metadata_json')
            ->implode(' ');
        self::assertStringNotContainsString((string) $account->email, $securityMetadata);
        self::assertStringNotContainsString($method->secret, $securityMetadata);
        self::assertStringNotContainsString($code, $securityMetadata);
        self::assertNotSame($sessionIdBeforeConfirmation, $this->app['session']->getId());
        self::assertSame(2, $this->app['session']->get(BrowserSession::LEVEL));
        self::assertSame('password+totp', $this->app['session']->get(BrowserSession::METHOD));
        self::assertIsString($this->app['session']->get(BrowserSession::AUTHENTICATED_AT));

        $this->getJson('/api/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('data.authentication.method', 'password+totp')
            ->assertJsonPath('data.authentication.level', 'aal2');

        $this->postJson(
            '/api/v1/auth/mfa/totp/enrollment-confirmations',
            ['code' => $code],
        )->assertConflict();
    }

    public function test_status_never_discloses_secret_or_recovery_material(): void
    {
        $account = IdentityAccount::factory()->create();
        $method = $this->activeMethod($account);
        $this->authenticateRecently($account);

        $response = $this->getJson('/api/v1/auth/mfa');

        $response
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.method', 'totp')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.recovery_codes_remaining', 0);
        $responseContent = $this->responseContent($response);
        $this->assertResponseContainsNoSecrets($responseContent);
        self::assertStringNotContainsString($method->secret, $responseContent);
    }

    public function test_enrollment_rejects_anonymous_stale_expired_and_foreign_sessions(): void
    {
        $this->postJson('/api/v1/auth/mfa/totp/enrollments')->assertUnauthorized();

        $staleAccount = IdentityAccount::factory()->create();
        $this->actingAs($staleAccount, 'web')->withSession($this->sessionMetadata(
            passwordAuthenticatedAt: $this->clock()->now()->modify('-11 minutes')->format(DATE_ATOM),
        ));

        $this->postJson('/api/v1/auth/mfa/totp/enrollments')->assertForbidden();

        $owner = IdentityAccount::factory()->create();
        $other = IdentityAccount::factory()->create();
        $this->pendingMethod($owner);
        $this->authenticateRecently($other);

        $this->postJson('/api/v1/auth/mfa/totp/enrollment-qr-disclosures')
            ->assertConflict();

        $this->postJson('/api/v1/auth/mfa/totp/enrollments')->assertCreated();
        $this->clock()->sleep(301);

        $this->postJson('/api/v1/auth/mfa/totp/enrollment-qr-disclosures')
            ->assertConflict();
    }

    public function test_enrollment_requests_reject_unknown_fields_invalid_codes_and_missing_csrf(): void
    {
        $account = IdentityAccount::factory()->create();
        $this->authenticateRecently($account);

        $this->postJson('/api/v1/auth/mfa/totp/enrollments', ['unexpected' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unexpected');

        $this->postJson('/api/v1/auth/mfa/totp/enrollments')->assertCreated();

        $this->postJson(
            '/api/v1/auth/mfa/totp/enrollment-qr-disclosures',
            ['unexpected' => true],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unexpected');

        $this->postJson('/api/v1/auth/mfa/totp/enrollment-qr-disclosures')->assertOk();

        $this->postJson(
            '/api/v1/auth/mfa/totp/enrollment-confirmations',
            ['code' => '12345', 'unexpected' => true],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'unexpected']);

        $this->app['env'] = 'local';
        $this->withSession([
            ...$this->sessionMetadata(),
            '_token' => 'expected-csrf-token',
        ])->postJson('/api/v1/auth/mfa/totp/enrollments')
            ->assertStatus(419)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    /**
     * Authenticate an account with fresh password evidence.
     */
    private function authenticateRecently(IdentityAccount $account): void
    {
        $this->actingAs($account, 'web')->withSession($this->sessionMetadata());
    }

    /**
     * Build server-owned authentication metadata for a recent AAL1 session.
     *
     * @return array<string, int|string>
     */
    private function sessionMetadata(?string $passwordAuthenticatedAt = null): array
    {
        $authenticatedAt = $passwordAuthenticatedAt ?? $this->clock()->now()->format(DATE_ATOM);

        return [
            BrowserSession::METHOD => 'password',
            BrowserSession::LEVEL => 1,
            BrowserSession::AUTHENTICATED_AT => $authenticatedAt,
            BrowserSession::PASSWORD_AUTHENTICATED_AT => $authenticatedAt,
            BrowserSession::PUBLIC_ID => '01983a6f-c400-7b03-88bc-15cb550905a9',
        ];
    }

    /**
     * Persist an active TOTP method for status scenarios.
     */
    private function activeMethod(IdentityAccount $account): IdentityMfaMethod
    {
        $method = $this->pendingMethod($account);
        $method->status = MfaMethodStatus::Active;
        $method->confirmed_at = CarbonImmutable::instance($this->clock()->now());
        $method->enrollment_expires_at = null;
        $method->save();

        return $method;
    }

    /**
     * Persist a pending TOTP method owned by the supplied identity.
     */
    private function pendingMethod(IdentityAccount $account): IdentityMfaMethod
    {
        $method = new IdentityMfaMethod;
        $method->identity_account_id = $account->id;
        $method->type = MfaMethodType::Totp;
        $method->status = MfaMethodStatus::Pending;
        $method->secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $method->enrollment_expires_at = CarbonImmutable::instance(
            $this->clock()->now()->modify('+5 minutes'),
        );
        $method->save();

        return $method;
    }

    /**
     * Generate the current six-digit code for an encrypted method secret.
     */
    private function codeFor(string $secret): string
    {
        $step = intdiv($this->clock()->now()->getTimestamp(), 30);

        return (new Google2FA)->oathTotp($secret, $step);
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
     * Assert that a response does not carry known secret field names.
     */
    private function assertResponseContainsNoSecrets(string $content): void
    {
        self::assertStringNotContainsString('"secret"', $content);
        self::assertStringNotContainsString('"code_hash"', $content);
        self::assertStringNotContainsString('"lookup_digest"', $content);
    }

    /**
     * Return a response body as a guaranteed string.
     *
     * @param  TestResponse<Response>  $response
     */
    private function responseContent(TestResponse $response): string
    {
        $content = $response->getContent();

        if (! is_string($content)) {
            throw new LogicException('The MFA test response must contain a string body.');
        }

        return $content;
    }
}
