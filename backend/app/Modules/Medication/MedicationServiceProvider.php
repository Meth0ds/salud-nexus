<?php

declare(strict_types=1);

namespace App\Modules\Medication;

use App\Modules\Medication\Application\Ports\MedicationEventPublisher;
use App\Modules\Medication\Infrastructure\Events\LaravelMedicationEventPublisher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Register medication adapters, routes, and mutation rate limits.
 */
final class MedicationServiceProvider extends ServiceProvider
{
    /**
     * Register medication application ports in the service container.
     */
    public function register(): void
    {
        $this->app->bind(MedicationEventPublisher::class, LaravelMedicationEventPublisher::class);
    }

    /**
     * Load medication routes and configure their per-actor mutation limiter.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        RateLimiter::for('patient.medication_mutation', static fn (Request $request): Limit => Limit::perMinute(10)
            ->by('patient-medication:'.hash('sha256', (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))));
    }
}
