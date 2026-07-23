<?php

declare(strict_types=1);

namespace App\Modules\Medication\Infrastructure\Events;

use App\Modules\Medication\Application\Ports\MedicationEventPublisher;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Publish medication domain events through Laravel's event dispatcher.
 */
final readonly class LaravelMedicationEventPublisher implements MedicationEventPublisher
{
    public function __construct(private Dispatcher $events) {}

    /**
     * Dispatch a committed medication event to synchronous and queued listeners.
     */
    public function publish(object $event): void
    {
        $this->events->dispatch($event);
    }
}
