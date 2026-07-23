<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

final class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-Battery-Staple-2026!';

    public function test_csrf_bootstrap_issues_the_expected_browser_cookies(): void
    {
        $response = $this->get('/api/v1/auth/csrf');

        $response
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN')
            ->assertCookie((string) config('session.cookie'));

        $cookies = collect($response->headers->getCookies());
        $sessionCookie = $cookies->first(
            static fn ($cookie): bool => $cookie->getName() === config('session.cookie'),
        );
        $xsrfCookie = $cookies->first(
            static fn ($cookie): bool => $cookie->getName() === 'XSRF-TOKEN',
        );

        self::assertNotNull($sessionCookie);
        self::assertTrue($sessionCookie->isHttpOnly());
        self::assertSame('lax', $sessionCookie->getSameSite());
        self::assertNotNull($xsrfCookie);
        self::assertFalse($xsrfCookie->isHttpOnly());
        self::assertSame('lax', $xsrfCookie->getSameSite());
    }

    public function test_password_login_authenticates_and_rotates_the_session_identifier(): void
    {
        $account = IdentityAccount::factory()->create([
            'email' => 'clinician@example.test',
            'password' => self::PASSWORD,
        ]);
        $csrfToken = 'csrf-login-token';

        $this->withSession(['_token' => $csrfToken]);
        $sessionIdBeforeLogin = $this->app['session']->getId();

        $this->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/login', [
                'email' => '  CLINICIAN@example.test ',
                'password' => self::PASSWORD,
            ])
            ->assertNoContent();

        $this->assertAuthenticatedAs($account, 'web');
        self::assertNotSame($sessionIdBeforeLogin, $this->app['session']->getId());
        self::assertSame('password', $this->app['session']->get('auth.method'));
        self::assertSame(1, $this->app['session']->get('auth.level'));
        self::assertIsString($this->app['session']->get('auth.authenticated_at'));
        self::assertIsString($this->app['session']->get('auth.password_authenticated_at'));
    }

    public function test_wrong_unknown_and_suspended_credentials_have_an_identical_public_response(): void
    {
        IdentityAccount::factory()->create([
            'email' => 'known@example.test',
            'password' => self::PASSWORD,
        ]);
        IdentityAccount::factory()->suspended()->create([
            'email' => 'suspended@example.test',
            'password' => self::PASSWORD,
        ]);
        $csrfToken = 'csrf-invalid-credentials';

        $wrongPassword = $this->withSession(['_token' => $csrfToken])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/login', [
                'email' => 'known@example.test',
                'password' => 'This-password-is-wrong!',
            ]);

        $unknownAccount = $this->withSession(['_token' => $csrfToken])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/login', [
                'email' => 'unknown@example.test',
                'password' => self::PASSWORD,
            ]);

        $suspendedAccount = $this->withSession(['_token' => $csrfToken])
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/login', [
                'email' => 'suspended@example.test',
                'password' => self::PASSWORD,
            ]);

        foreach ([$wrongPassword, $unknownAccount, $suspendedAccount] as $response) {
            $response
                ->assertUnauthorized()
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('type', 'https://salud-nexus.example/problems/unauthenticated')
                ->assertJsonPath('detail', 'Authentication is required to access this resource.');
        }

        $publicPayload = static fn ($response): array => Arr::except(
            $response->json(),
            ['request_id'],
        );

        self::assertSame($publicPayload($wrongPassword), $publicPayload($unknownAccount));
        self::assertSame($publicPayload($wrongPassword), $publicPayload($suspendedAccount));
        $this->assertGuest('web');
    }

    public function test_login_mutation_rejects_a_missing_csrf_proof(): void
    {
        IdentityAccount::factory()->create([
            'email' => 'csrf@example.test',
            'password' => self::PASSWORD,
        ]);
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'expected-csrf-token'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'csrf@example.test',
                'password' => self::PASSWORD,
            ])
            ->assertStatus(419)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/csrf-token-mismatch');

        $this->assertGuest('web');
    }

    public function test_current_session_is_protected_and_discloses_only_minimal_capabilities(): void
    {
        $this->getJson('/api/v1/auth/session')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json');

        $account = IdentityAccount::factory()->create([
            'display_name' => 'Dra. Vega',
            'email' => 'vega@example.test',
        ]);

        $response = $this->actingAs($account, 'web')
            ->withSession([
                'auth.method' => 'password',
                'auth.level' => 1,
                'auth.authenticated_at' => '2026-07-19T09:30:00+00:00',
            ])
            ->getJson('/api/v1/auth/session');

        $response
            ->assertOk()
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.identity.id', $account->public_id)
            ->assertJsonPath('data.identity.display_name', 'Dra. Vega')
            ->assertJsonPath('data.authentication.method', 'password')
            ->assertJsonPath('data.authentication.level', 'aal1')
            ->assertJsonPath('data.capabilities.0', 'session:read')
            ->assertJsonPath('data.capabilities.1', 'session:logout')
            ->assertJsonStructure(['meta' => ['request_id']]);

        $identityPayload = $response->json('data.identity');
        self::assertIsArray($identityPayload);
        self::assertArrayNotHasKey('email', $identityPayload);
        self::assertArrayNotHasKey('password', $identityPayload);
    }

    public function test_personal_access_tokens_are_not_an_enabled_authentication_path(): void
    {
        $this->withToken('untrusted-bearer-token')
            ->getJson('/api/v1/auth/session')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->get('/sanctum/csrf-cookie')->assertNotFound();
    }

    public function test_logout_invalidates_the_session_regenerates_csrf_and_removes_authentication(): void
    {
        $account = IdentityAccount::factory()->create();
        $csrfToken = 'csrf-logout-token';
        $this->app['env'] = 'local';

        $this->actingAs($account, 'web')->withSession([
            '_token' => $csrfToken,
            'sensitive-session-marker' => 'must-be-erased',
            'auth.method' => 'password',
            'auth.level' => 1,
        ]);
        $sessionIdBeforeLogout = $this->app['session']->getId();

        $this->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent()
            ->assertSessionMissing('sensitive-session-marker');

        $this->assertGuest('web');
        self::assertNotSame($sessionIdBeforeLogout, $this->app['session']->getId());
        self::assertNotSame($csrfToken, $this->app['session']->token());
    }

    public function test_login_is_throttled_by_normalized_account_and_ip(): void
    {
        config()->set('identity.rate_limits.login_account_ip_per_minute', 2);
        config()->set('identity.rate_limits.login_ip_per_minute', 20);
        $csrfToken = 'csrf-rate-limit-token';
        $this->withSession(['_token' => $csrfToken])
            ->withHeader('X-CSRF-TOKEN', $csrfToken);

        $payload = [
            'email' => 'rate-limited@example.test',
            'password' => self::PASSWORD,
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', [
            ...$payload,
            'email' => '  RATE-LIMITED@example.test ',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertTooManyRequests()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/too-many-requests');
    }
}
