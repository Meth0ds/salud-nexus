<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Support\Health\ReadinessProbe;
use App\Support\Health\ReadinessResult;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function test_liveness_does_not_depend_on_external_services(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertJsonPath('data.status', 'alive')
            ->assertJsonPath('data.checks.application', 'ok')
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_readiness_reports_database_availability(): void
    {
        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.checks.database', 'ok');
    }

    public function test_readiness_fails_closed_without_disclosing_the_dependency_error(): void
    {
        $this->app->instance(ReadinessProbe::class, new class implements ReadinessProbe
        {
            public function check(): ReadinessResult
            {
                return ReadinessResult::notReady('database');
            }
        });

        $response = $this->getJson('/api/v1/health/ready');

        $response
            ->assertStatus(503)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/service-unavailable')
            ->assertJsonPath('title', 'Service unavailable')
            ->assertJsonPath('status', 503)
            ->assertJsonPath('detail', 'The service is not ready to receive traffic.');

        self::assertStringNotContainsString('SQL', (string) $response->getContent());
    }
}
