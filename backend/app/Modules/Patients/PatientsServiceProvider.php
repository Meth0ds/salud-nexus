<?php

declare(strict_types=1);

namespace App\Modules\Patients;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Load patient routes and configure actor-scoped mutation rate limits.
 */
final class PatientsServiceProvider extends ServiceProvider
{
    /**
     * Register patient routes and their booking and change limiters.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        RateLimiter::for('patient.booking', static fn (Request $request): Limit => Limit::perMinute(20)
            ->by('patient-booking:'.(string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('patient.appointment_change', static fn (Request $request): Limit => Limit::perMinute(10)
            ->by('patient-appointment-change:'.hash(
                'sha256',
                (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
            )));
    }
}
