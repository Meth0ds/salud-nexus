<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Routing\Router;
use JsonException;
use RuntimeException;
use Tests\TestCase;

/**
 * Verify that the Laravel routes and OpenAPI contract remain synchronized.
 */
final class OpenApiContractTest extends TestCase
{
    /**
     * Define the HTTP methods represented by OpenAPI operations.
     *
     * @var list<string>
     */
    private const HTTP_METHODS = ['delete', 'get', 'patch', 'post', 'put'];

    /**
     * Define operations that do not require an authenticated browser session.
     *
     * @var list<string>
     */
    private const PUBLIC_OPERATIONS = [
        'get /',
        'get /auth/csrf',
        'post /auth/login',
        'get /health/live',
        'get /health/ready',
    ];

    /**
     * Verify that each named API route has exactly one documented operation.
     *
     * @throws JsonException
     */
    public function test_every_named_api_route_is_documented_once_and_only_once(): void
    {
        $specification = $this->specification();

        self::assertSame(
            $this->implementedOperations(),
            $this->documentedOperations($specification),
            'The Laravel route table and OpenAPI paths have drifted.',
        );
    }

    /**
     * Verify contract identity, local references, and shared problem responses.
     *
     * @throws JsonException
     */
    public function test_contract_has_unique_operations_internal_references_and_problem_responses(): void
    {
        $specification = $this->specification();

        self::assertSame('3.1.1', $specification['openapi'] ?? null);
        self::assertSame(
            'https://json-schema.org/draft/2020-12/schema',
            $specification['jsonSchemaDialect'] ?? null,
        );

        $encoded = json_encode($specification, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertDoesNotMatchRegularExpression(
            '/"\$ref":"(?!#\/)/',
            $encoded,
            'External references make contract generation dependent on the network.',
        );

        $operationIds = [];
        foreach ($this->operations($specification) as $operationKey => $operation) {
            $operationId = $operation['operationId'] ?? null;
            self::assertIsString($operationId, $operationKey.' must define operationId.');
            self::assertMatchesRegularExpression('/^[a-z][A-Za-z0-9]+$/D', $operationId);
            self::assertArrayNotHasKey($operationId, $operationIds, 'Duplicate operationId: '.$operationId);
            $operationIds[$operationId] = true;

            $responses = $operation['responses'] ?? null;
            self::assertIsArray($responses, $operationKey.' must define responses.');
            self::assertSame(
                ['$ref' => '#/components/responses/Problem'],
                $responses['default'] ?? null,
                $operationKey.' must expose the common RFC 9457 error contract.',
            );
            self::assertNotEmpty(
                array_filter(
                    array_keys($responses),
                    static fn (int|string $status): bool => preg_match('/^2\d\d$/D', (string) $status) === 1,
                ),
                $operationKey.' must document a successful response.',
            );
        }
    }

    /**
     * Verify session and CSRF requirements for every documented operation.
     *
     * @throws JsonException
     */
    public function test_session_and_csrf_security_are_explicit_per_operation(): void
    {
        foreach ($this->operations($this->specification()) as $operationKey => $operation) {
            $security = $operation['security'] ?? [];
            self::assertIsArray($security, $operationKey.' has malformed security requirements.');

            if (in_array($operationKey, self::PUBLIC_OPERATIONS, true)) {
                if ($operationKey === 'post /auth/login') {
                    self::assertTrue($this->securityContains($security, 'xsrfHeader'));
                    self::assertFalse($this->securityContains($security, 'sessionCookie'));
                } else {
                    self::assertSame([], $security, $operationKey.' must remain publicly callable.');
                }

                continue;
            }

            self::assertTrue(
                $this->securityContains($security, 'sessionCookie'),
                $operationKey.' must require the Sanctum browser session.',
            );

            if (str_starts_with($operationKey, 'post ')) {
                self::assertTrue(
                    $this->securityContains($security, 'xsrfHeader'),
                    $operationKey.' must require CSRF protection.',
                );
            }
        }
    }

    /**
     * Decode the local OpenAPI document into its array representation.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function specification(): array
    {
        $path = base_path('openapi/openapi.json');
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException('The OpenAPI contract is missing or unreadable.');
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('The OpenAPI contract must decode to an object.');
        }

        /**
         * Preserve the decoded object shape for static analysis.
         *
         * @var array<string, mixed> $decoded
         */
        return $decoded;
    }

    /**
     * Collect the normalized operations implemented by named Laravel routes.
     *
     * @return list<string>
     */
    private function implementedOperations(): array
    {
        /**
         * Resolve the concrete router used by the application.
         *
         * @var Router $router
         */
        $router = $this->app->make(Router::class);
        $operations = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($route->getName() === null) {
                continue;
            }

            $uri = $route->uri();
            if ($uri !== 'api/v1' && ! str_starts_with($uri, 'api/v1/')) {
                continue;
            }

            $path = substr($uri, strlen('api/v1'));
            $path = $path === '' ? '/' : '/'.ltrim($path, '/');
            foreach ($route->methods() as $method) {
                $normalizedMethod = strtolower($method);
                if (in_array($normalizedMethod, self::HTTP_METHODS, true)) {
                    $operations[] = $normalizedMethod.' '.$path;
                }
            }
        }

        sort($operations);

        return $operations;
    }

    /**
     * Collect the normalized operations documented by OpenAPI.
     *
     * @param  array<string, mixed>  $specification
     * @return list<string>
     */
    private function documentedOperations(array $specification): array
    {
        $operations = array_keys($this->operations($specification));
        sort($operations);

        return $operations;
    }

    /**
     * Index every OpenAPI operation by its normalized method and path.
     *
     * @param  array<string, mixed>  $specification
     * @return array<string, array<string, mixed>>
     */
    private function operations(array $specification): array
    {
        $paths = $specification['paths'] ?? null;
        self::assertIsArray($paths, 'OpenAPI must define paths.');
        $operations = [];

        foreach ($paths as $path => $pathItem) {
            self::assertIsString($path);
            self::assertIsArray($pathItem, $path.' must be a Path Item Object.');

            foreach (self::HTTP_METHODS as $method) {
                $operation = $pathItem[$method] ?? null;
                if ($operation === null) {
                    continue;
                }

                self::assertIsArray($operation, $method.' '.$path.' must be an Operation Object.');
                /**
                 * Preserve the OpenAPI operation shape for static analysis.
                 *
                 * @var array<string, mixed> $operation
                 */
                $operations[$method.' '.$path] = $operation;
            }
        }

        return $operations;
    }

    /**
     * Determine whether a security requirement includes the requested scheme.
     *
     * @param  array<mixed>  $security
     */
    private function securityContains(array $security, string $scheme): bool
    {
        foreach ($security as $requirement) {
            if (is_array($requirement) && array_key_exists($scheme, $requirement)) {
                return true;
            }
        }

        return false;
    }
}
