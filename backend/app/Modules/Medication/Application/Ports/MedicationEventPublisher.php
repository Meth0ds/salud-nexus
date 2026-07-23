<?php

declare(strict_types=1);

namespace App\Modules\Medication\Application\Ports;

/**
 * Define the infrastructure boundary used to publish medication events.
 */
interface MedicationEventPublisher
{
    /**
     * Publish one immutable medication event.
     */
    public function publish(object $event): void;
}
