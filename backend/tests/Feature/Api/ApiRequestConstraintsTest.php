<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ApiRequestConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/v1/_test/constraints', static fn (): JsonResponse => response()->json([
            'accepted' => true,
        ]));
    }

    public function test_state_changing_requests_with_a_body_require_json(): void
    {
        $this->call(
            method: 'POST',
            uri: '/api/v1/_test/constraints',
            parameters: ['name' => 'Ada'],
        )
            ->assertUnsupportedMediaType()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/unsupported-media-type');
    }

    public function test_standard_and_structured_json_media_types_are_accepted(): void
    {
        $this->call(
            method: 'POST',
            uri: '/api/v1/_test/constraints',
            server: ['CONTENT_TYPE' => 'application/json; charset=utf-8'],
            content: '{"name":"Ada"}',
        )
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->call(
            method: 'POST',
            uri: '/api/v1/_test/constraints',
            server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: '{"name":"Grace"}',
        )
            ->assertOk();
    }

    public function test_body_size_is_enforced_even_without_a_content_length_header(): void
    {
        config()->set('api.requests.max_body_bytes', 16);

        $this->call(
            method: 'POST',
            uri: '/api/v1/_test/constraints',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['payload' => str_repeat('x', 32)], JSON_THROW_ON_ERROR),
        )
            ->assertStatus(413)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/payload-too-large');
    }

    public function test_empty_mutating_requests_and_safe_methods_do_not_require_a_media_type(): void
    {
        $this->call('POST', '/api/v1/_test/constraints')
            ->assertOk();

        $this->getJson('/api/v1')
            ->assertOk();
    }
}
