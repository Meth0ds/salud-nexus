<?php

declare(strict_types=1);

namespace App\Modules\Scheduling;

use App\Modules\Scheduling\Application\Ports\DomainEventPublisher;
use App\Modules\Scheduling\Infrastructure\Events\LaravelDomainEventPublisher;
use Illuminate\Support\ServiceProvider;
use LogicException;

/**
 * Register scheduling adapters and validate operational policy configuration.
 */
final class SchedulingServiceProvider extends ServiceProvider
{
    /**
     * Register scheduling application ports in the service container.
     */
    public function register(): void
    {
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);
    }

    /**
     * Fail startup when the appointment-change cutoff is outside safe bounds.
     */
    public function boot(): void
    {
        $cutoffMinutes = config('scheduling.patient_change_cutoff_minutes');

        if (! is_int($cutoffMinutes) || $cutoffMinutes < 0 || $cutoffMinutes > 43_200) {
            throw new LogicException('The patient appointment change cutoff must be between zero and 43,200 minutes.');
        }
    }
}
