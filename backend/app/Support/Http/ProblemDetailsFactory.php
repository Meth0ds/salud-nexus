<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Convert application failures into sanitized RFC 9457 API responses.
 */
final readonly class ProblemDetailsFactory
{
    public function __construct(private SecureResponseHeaders $secureHeaders) {}

    /**
     * Build a secure problem-details response for one thrown exception.
     */
    public function fromThrowable(Throwable $exception, Request $request): JsonResponse
    {
        [$status, $slug, $title, $detail] = $this->describe($exception);
        $requestId = $this->requestId($request);

        $problem = [
            'type' => config('api.problem_type_base').'/'.$slug,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getPathInfo(),
            'request_id' => $requestId,
        ];

        if ($exception instanceof ValidationException) {
            $problem['errors'] = $exception->errors();
        }

        $response = new JsonResponse(
            data: $problem,
            status: $status,
            headers: $this->safeExceptionHeaders($exception),
            options: JSON_UNESCAPED_SLASHES,
        );
        $response->headers->set('Content-Type', 'application/problem+json');
        $response->headers->set((string) config('api.request_id.header'), $requestId);

        $this->secureHeaders->apply($response, $request);

        return $response;
    }

    /**
     * Map an exception to its public HTTP status, slug, title, and detail.
     *
     * @return array{int, string, string, string}
     */
    private function describe(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof ValidationException => [
                422,
                'validation-error',
                'Validation failed',
                'One or more request fields are invalid.',
            ],
            $exception instanceof AuthenticationException => [
                401,
                'unauthenticated',
                'Authentication required',
                'Authentication is required to access this resource.',
            ],
            $exception instanceof AuthorizationException => [
                403,
                'forbidden',
                'Access denied',
                'You are not allowed to perform this action.',
            ],
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => [
                404,
                'not-found',
                'Resource not found',
                'The requested API resource does not exist.',
            ],
            $exception instanceof MethodNotAllowedHttpException => [
                405,
                'method-not-allowed',
                'Method not allowed',
                'The HTTP method is not supported by this resource.',
            ],
            $exception instanceof TokenMismatchException => [
                419,
                'csrf-token-mismatch',
                'Page expired',
                'The CSRF token is missing or no longer valid.',
            ],
            $this->statusOf($exception) === 419 => [
                419,
                'csrf-token-mismatch',
                'Page expired',
                'The CSRF token is missing or no longer valid.',
            ],
            $this->statusOf($exception) === 400 => [
                400,
                'bad-request',
                'Invalid request',
                'The request could not be processed.',
            ],
            $this->statusOf($exception) === 409 => [
                409,
                'conflict',
                'Resource conflict',
                'The request conflicts with the current resource state.',
            ],
            $this->statusOf($exception) === 413 => [
                413,
                'payload-too-large',
                'Payload too large',
                'The request payload exceeds the allowed size.',
            ],
            $this->statusOf($exception) === 415 => [
                415,
                'unsupported-media-type',
                'Unsupported media type',
                'The request media type is not supported.',
            ],
            $this->statusOf($exception) === 429 => [
                429,
                'too-many-requests',
                'Too many requests',
                'The request rate limit has been exceeded.',
            ],
            $this->statusOf($exception) === 503 => [
                503,
                'service-unavailable',
                'Service unavailable',
                'The service is not ready to receive traffic.',
            ],
            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(),
                'http-error',
                'Request failed',
                'The request could not be completed.',
            ],
            default => [
                500,
                'internal-server-error',
                'Internal server error',
                'An unexpected error occurred.',
            ],
        };
    }

    /**
     * Return the status carried by an HTTP exception, when available.
     */
    private function statusOf(Throwable $exception): ?int
    {
        return $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : null;
    }

    /**
     * Forward only response headers that are safe and useful to API clients.
     *
     * @return array<string, string>
     */
    private function safeExceptionHeaders(Throwable $exception): array
    {
        if (! $exception instanceof HttpExceptionInterface) {
            return [];
        }

        return array_intersect_key(
            $exception->getHeaders(),
            array_flip(['Allow', 'Retry-After', 'WWW-Authenticate']),
        );
    }

    /**
     * Return the normalized request ID, generating one when middleware did not.
     */
    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get(AssignRequestId::ATTRIBUTE);

        if (is_string($requestId) && Str::isUuid($requestId)) {
            return strtolower($requestId);
        }

        $requestId = Str::uuid7()->toString();
        $request->attributes->set(AssignRequestId::ATTRIBUTE, $requestId);

        return $requestId;
    }
}
