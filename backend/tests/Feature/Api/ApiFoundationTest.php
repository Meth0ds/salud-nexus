<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ApiFoundationTest extends TestCase
{
    public function test_api_index_exposes_a_versioned_contract_and_request_id(): void
    {
        $response = $this->getJson('/api/v1');

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Salud Nexus API')
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonPath('data.status', 'available')
            ->assertJsonStructure(['data' => ['name', 'version', 'status'], 'meta' => ['request_id']]);

        $requestId = (string) $response->headers->get('X-Request-ID');

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $requestId,
        );
        self::assertSame($requestId, $response->json('meta.request_id'));
    }

    public function test_a_valid_client_request_id_is_preserved_and_an_invalid_one_is_replaced(): void
    {
        $validRequestId = '018f47a2-4f4a-7b0f-8b15-9f82558b5924';

        $this->withHeader('X-Request-ID', $validRequestId)
            ->getJson('/api/v1')
            ->assertHeader('X-Request-ID', $validRequestId)
            ->assertJsonPath('meta.request_id', $validRequestId);

        $response = $this->withHeader('X-Request-ID', "invalid\r\nInjected: true")
            ->getJson('/api/v1');

        $response->assertOk();
        self::assertNotSame("invalid\r\nInjected: true", $response->headers->get('X-Request-ID'));

        $nilUuidResponse = $this->withHeader('X-Request-ID', '00000000-0000-0000-0000-000000000000')
            ->getJson('/api/v1');

        self::assertNotSame(
            '00000000-0000-0000-0000-000000000000',
            $nilUuidResponse->headers->get('X-Request-ID'),
        );
    }

    public function test_api_responses_include_defensive_security_headers(): void
    {
        $this->getJson('/api/v1')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_hsts_is_only_emitted_when_https_and_explicitly_enabled(): void
    {
        config()->set('api.hsts.enabled', true);

        $this->getJson('https://localhost/api/v1')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $this->getJson('http://localhost/api/v1')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_cors_only_allows_configured_origins(): void
    {
        $this->call('OPTIONS', '/api/v1', server: [
            'HTTP_ORIGIN' => 'http://localhost:4200',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4200');

        $this->call('OPTIONS', '/api/v1', server: [
            'HTTP_ORIGIN' => 'https://attacker.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_unknown_api_routes_use_rfc_9457_problem_details(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/not-found')
            ->assertJsonPath('title', 'Resource not found')
            ->assertJsonPath('status', 404)
            ->assertJsonPath('detail', 'The requested API resource does not exist.')
            ->assertJsonPath('instance', '/api/v1/does-not-exist')
            ->assertJsonStructure(['request_id']);

        self::assertSame($response->headers->get('X-Request-ID'), $response->json('request_id'));
    }

    public function test_validation_and_method_errors_keep_the_problem_details_contract(): void
    {
        Route::post('/api/v1/_test/validation', static function (Request $request): void {
            validator($request->all(), [
                'email' => ['required', 'email:rfc'],
            ])->validate();
        });

        $this->postJson('/api/v1/_test/validation', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/validation-error')
            ->assertJsonPath('status', 422)
            ->assertJsonStructure(['errors' => ['email']]);

        $this->postJson('/api/v1')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/method-not-allowed');
    }

    public function test_unexpected_exceptions_never_disclose_internal_details(): void
    {
        $logSpy = Log::spy();

        Route::get('/api/v1/_test/failure', static function (): never {
            throw new RuntimeException('database-password-must-never-leak');
        });

        $response = $this->getJson('/api/v1/_test/failure');

        $response
            ->assertInternalServerError()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/internal-server-error')
            ->assertJsonPath('status', 500)
            ->assertJsonPath('detail', 'An unexpected error occurred.');

        self::assertStringNotContainsString('database-password', (string) $response->getContent());
        self::assertStringNotContainsString('RuntimeException', (string) $response->getContent());

        $logSpy->shouldHaveReceived(
            'error',
            [
                'Unhandled application exception.',
                Mockery::on(static fn (array $context): bool => $context['exception_class'] === RuntimeException::class
                    && is_string($context['fingerprint'])
                    && strlen($context['fingerprint']) === 64
                    && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'database-password')),
            ],
        );
    }
}
