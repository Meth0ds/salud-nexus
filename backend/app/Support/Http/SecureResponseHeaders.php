<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply a restrictive, privacy-preserving HTTP response policy.
 */
final class SecureResponseHeaders
{
    /**
     * Harden one API response, enabling HSTS only for verified HTTPS requests.
     */
    public function apply(Response $response, Request $request): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        if ($request->isSecure() && config()->boolean('api.hsts.enabled')) {
            $directives = ['max-age='.(int) config('api.hsts.max_age')];

            if (config()->boolean('api.hsts.include_subdomains')) {
                $directives[] = 'includeSubDomains';
            }

            if (config()->boolean('api.hsts.preload')) {
                $directives[] = 'preload';
            }

            $response->headers->set('Strict-Transport-Security', implode('; ', $directives));
        }

        return $response;
    }
}
