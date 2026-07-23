<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalize request correlation IDs and propagate them through application context.
 */
final class AssignRequestId
{
    public const ATTRIBUTE = 'request_id';

    /**
     * Handle an incoming request and echo its trusted or generated correlation ID.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = (string) config('api.request_id.header', 'X-Request-ID');
        $candidate = $request->headers->get($headerName);
        $requestId = is_string($candidate) && $this->isSupportedUuid($candidate)
            ? strtolower($candidate)
            : Str::uuid7()->toString();

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set($headerName, $requestId);

        return $response;
    }

    /**
     * Accept only non-sequential client UUIDs or server-compatible UUIDv7 values.
     */
    private function isSupportedUuid(string $candidate): bool
    {
        return Str::isUuid($candidate, 4) || Str::isUuid($candidate, 7);
    }
}
