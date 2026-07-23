<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Enforce bounded JSON request bodies before controllers deserialize input.
 */
final readonly class EnforceApiRequestConstraints
{
    /**
     * Handle an incoming API request and reject unsafe representations.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*') || ! $this->canCarryRepresentation($request)) {
            return $next($request);
        }

        $rawContent = $request->getContent();
        $actualBytes = strlen($rawContent);
        $declaredBytes = $this->declaredContentLength($request);
        $maximumBytes = (int) config('api.requests.max_body_bytes');

        if ($maximumBytes < 1 || max($actualBytes, $declaredBytes) > $maximumBytes) {
            throw new HttpException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $hasBody = $actualBytes > 0
            || $declaredBytes > 0
            || $request->request->count() > 0
            || $request->files->count() > 0;

        if ($hasBody && ! $this->isJsonMediaType($request)) {
            throw new UnsupportedMediaTypeHttpException;
        }

        return $next($request);
    }

    /**
     * Determine whether the HTTP method may carry a representation.
     */
    private function canCarryRepresentation(Request $request): bool
    {
        return in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true);
    }

    /**
     * Parse a valid non-negative Content-Length value without overflowing.
     */
    private function declaredContentLength(Request $request): int
    {
        $header = $request->headers->get('Content-Length');

        if (! is_string($header) || preg_match('/^\d+$/D', $header) !== 1) {
            return 0;
        }

        return min((int) $header, PHP_INT_MAX);
    }

    /**
     * Accept JSON and registered structured-syntax JSON media types only.
     */
    private function isJsonMediaType(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type');

        if (! is_string($contentType)) {
            return false;
        }

        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return preg_match('/^application\/(?:[a-z0-9!#$&^_.+-]+\+)?json$/D', $mediaType) === 1;
    }
}
