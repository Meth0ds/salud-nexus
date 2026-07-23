<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Application\BrowserSession;
use App\Modules\Identity\Application\RecoveryCodeManager;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use LogicException;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Verify session-bound MFA login challenges and AAL2 establishment.
 */
final class MfaLoginChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-Battery-Staple-2026!';

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            ClockInterface::class,
            new MockClock('2026-07-23T12:00:00+00:00'),
        );
    }

    public function test_password_with_active_mfa_returns_a_guest_challenge_then_establishes_aal2(): void
    {
        $account = $this->accountWithMfa();
        $sessionIdBeforePassword = $this->app['session']->getId();
        $challengeResponse = $this->login($account);

        $challengeResponse
            ->assertStatus(202)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.intent', 'login')
            ->assertJsonPath('data.methods.0', 'totp')
            ->assertJsonPath('data.attempts_remaining', 5)
            ->assertJsonStructure([
                'data' => ['challenge_id', 'expires_at'],
                'meta' => ['request_id'],
            ]);
        $this->assertGuest('web');
        self::assertNotSame($sessionIdBeforePassword, $this->app['session']->getId());
        self::assertNull($this->app['session']->get(BrowserSession::LEVEL));
        $challengeContent = $this->responseContent($challengeResponse);
        self::assertStringNotContainsString((string) $account->email, $challengeContent);
        self::assertStringNotContainsString(self::SECRET, $challengeContent);

        $challengeId = $challengeResponse->json('data.challenge_id');
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
        self::assertSame('password+totp', $this->app['session']->get(BrowserSession::METHOD));
        self::assertIsString(
            $this->app['session']->get(BrowserSession::PASSWORD_AUTHENTICATED_AT),
        );
        self::assertNull($this->app['session']->get(BrowserSession::MFA_CHALLENGE));

        $this->getJson('/api/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('data.authentication.level', 'aal2');
        $this->assertDatabaseHas('identity_security_events', [
            'identity_account_id' => $account->id,
            'event_type' => 'mfa.challenge.issued',
            'result' => 'succeeded',
            'authentication_level' => 1,
        ]);
        $this->assertDatabaseHas('identity_security_events', [
            'identity_account_id' => $account->id,
            'event_type' => 'mfa.challenge.succeeded',
            'result' => 'succeeded',
            'authentication_level' => 2,
        ]);
    }

    public function test_wrong_expired_exhausted_and_unbound_challenges_share_a_public_failure(): void
    {
        config()->set('identity.mfa.max_attempts', 2);
        $account = $this->accountWithMfa();
        $challenge = $this->login($account);
        $challengeId = $challenge->json('data.challenge_id');
        self::assertIsString($challengeId);
        $wrongCode = $this->differentCode($this->currentCode());

        $firstFailure = $this->verify($challengeId, 'totp', $wrongCode);
        $secondFailure = $this->verify($challengeId, 'totp', $wrongCode);
        $exhaustedFailure = $this->verify($challengeId, 'totp', $wrongCode);

        foreach ([$firstFailure, $secondFailure, $exhaustedFailure] as $failure) {
            $failure
                ->assertUnauthorized()
                ->assertHeader('Content-Type', 'application/problem+json');
        }

        $publicPayload = static fn (TestResponse $response): array => Arr::except(
            $response->json(),
            ['request_id'],
        );
        self::assertSame($publicPayload($firstFailure), $publicPayload($secondFailure));
        self::assertSame($publicPayload($firstFailure), $publicPayload($exhaustedFailure));
        self::assertNull($this->app['session']->get(BrowserSession::MFA_CHALLENGE));
        $this->assertGuest('web');

        $expiredChallenge = $this->login($account);
        $expiredId = $expiredChallenge->json('data.challenge_id');
        self::assertIsString($expiredId);
        $this->clock()->sleep(601);
        $expiredFailure = $this->verify($expiredId, 'totp', $this->currentCode());
        $expiredFailure->assertUnauthorized();
        self::assertSame($publicPayload($firstFailure), $publicPayload($expiredFailure));

        $unboundChallenge = $this->login($account);
        $unboundId = $unboundChallenge->json('data.challenge_id');
        self::assertIsString($unboundId);
        $this->app['session']->regenerate(true);
        $this->app['session']->forget(BrowserSession::MFA_CHALLENGE);
        $unboundFailure = $this->verify($unboundId, 'totp', $this->currentCode());
        $unboundFailure->assertUnauthorized();
        self::assertSame($publicPayload($firstFailure), $publicPayload($unboundFailure));
    }

    public function test_a_recovery_code_establishes_aal2_and_is_consumed_once(): void
    {
        $account = $this->accountWithMfa();
        $method = $this->activeMethodFor($account);
        $recoveryCode = $this->app->make(RecoveryCodeManager::class)
            ->generateFor($method)[0];
        $challenge = $this->login($account);

        $challenge
            ->assertJsonPath('data.methods.0', 'totp')
            ->assertJsonPath('data.methods.1', 'recovery');
        $challengeId = $challenge->json('data.challenge_id');
        self::assertIsString($challengeId);

        $this->verify($challengeId, 'recovery', strtolower($recoveryCode))
            ->assertNoContent();

        $this->assertAuthenticatedAs($account, 'web');
        self::assertSame('password+recovery', $this->app['session']->get(BrowserSession::METHOD));
        self::assertSame(1, $method->recoveryCodes()->whereNotNull('used_at')->count());
        $this->assertDatabaseHas('identity_security_events', [
            'identity_account_id' => $account->id,
            'event_type' => 'mfa.recovery.consumed',
            'result' => 'succeeded',
        ]);
    }

    public function test_a_consumed_totp_cannot_be_replayed_in_a_new_login_challenge(): void
    {
        $account = $this->accountWithMfa();
        $code = $this->currentCode();
        $firstChallenge = $this->login($account);
        $firstId = $firstChallenge->json('data.challenge_id');
        self::assertIsString($firstId);

        $this->verify($firstId, 'totp', $code)->assertNoContent();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $secondChallenge = $this->login($account);
        $secondId = $secondChallenge->json('data.challenge_id');
        self::assertIsString($secondId);
        $this->verify($secondId, 'totp', $code)->assertUnauthorized();

        $this->clock()->sleep(30);
        $this->verify($secondId, 'totp', $this->currentCode())->assertNoContent();
        $this->assertAuthenticatedAs($account, 'web');
    }

    public function test_challenge_verification_rejects_unknown_fields_invalid_shapes_and_missing_csrf(): void
    {
        $account = $this->accountWithMfa();
        $challenge = $this->login($account);
        $challengeId = $challenge->json('data.challenge_id');
        self::assertIsString($challengeId);

        $this->postJson('/api/v1/auth/mfa/challenge-verifications', [
            'challenge_id' => $challengeId,
            'method' => 'sms',
            'code' => '12345',
            'unexpected' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method', 'code', 'unexpected']);

        $this->app['env'] = 'local';
        $this->withSession([
            BrowserSession::MFA_CHALLENGE => $this->app['session']->get(
                BrowserSession::MFA_CHALLENGE,
            ),
            '_token' => 'expected-csrf-token',
        ])->postJson('/api/v1/auth/mfa/challenge-verifications', [
            'challenge_id' => $challengeId,
            'method' => 'totp',
            'code' => $this->currentCode(),
        ])
            ->assertStatus(419)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    /**
     * Create an active identity with one TOTP method.
     */
    private function accountWithMfa(): IdentityAccount
    {
        $account = IdentityAccount::factory()->create([
            'email' => 'mfa-patient-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => self::PASSWORD,
        ]);
        $this->activeMethodFor($account);

        return $account;
    }

    /**
     * Return or create the active TOTP method for an identity.
     */
    private function activeMethodFor(IdentityAccount $account): IdentityMfaMethod
    {
        $existing = IdentityMfaMethod::query()
            ->where('identity_account_id', $account->id)
            ->first();

        if ($existing instanceof IdentityMfaMethod) {
            return $existing;
        }

        $method = new IdentityMfaMethod;
        $method->identity_account_id = $account->id;
        $method->type = MfaMethodType::Totp;
        $method->status = MfaMethodStatus::Active;
        $method->secret = self::SECRET;
        $method->confirmed_at = CarbonImmutable::instance($this->clock()->now());
        $method->save();

        return $method;
    }

    /**
     * Submit valid password credentials for the supplied identity.
     *
     * @return TestResponse<Response>
     */
    private function login(IdentityAccount $account): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $account->email,
            'password' => self::PASSWORD,
        ]);
    }

    /**
     * Submit one factor against a pending login challenge.
     *
     * @return TestResponse<Response>
     */
    private function verify(string $challengeId, string $method, string $code): TestResponse
    {
        return $this->postJson('/api/v1/auth/mfa/challenge-verifications', [
            'challenge_id' => $challengeId,
            'method' => $method,
            'code' => $code,
        ]);
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

    /**
     * Return a response body as a guaranteed string.
     *
     * @param  TestResponse<Response>  $response
     */
    private function responseContent(TestResponse $response): string
    {
        $content = $response->getContent();

        if (! is_string($content)) {
            throw new LogicException('The MFA challenge response must contain a string body.');
        }

        return $content;
    }
}
