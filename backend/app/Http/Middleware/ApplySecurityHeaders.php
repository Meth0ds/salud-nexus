<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\SecureResponseHeaders;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply the hardened response-header policy to versioned API responses.
 */
final readonly class ApplySecurityHeaders
{
    public function __construct(private SecureResponseHeaders $headers) {}

    /**
     * Handle an incoming request and secure the resulting API response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $request->is('api/*')
            ? $this->headers->apply($response, $request)
            : $response;
    }
}
