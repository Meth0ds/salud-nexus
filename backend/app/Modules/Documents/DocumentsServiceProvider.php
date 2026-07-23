<?php

declare(strict_types=1);

namespace App\Modules\Documents;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Load document routes and protect download operations with per-actor limits.
 */
final class DocumentsServiceProvider extends ServiceProvider
{
    /**
     * Register document routes and their privacy-preserving rate limiter.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        RateLimiter::for('patient.document_download', static fn (Request $request): Limit => Limit::perMinute(20)
            ->by('patient-document:'.hash('sha256', (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))));
    }
}
