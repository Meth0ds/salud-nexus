<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application\Ports;

use App\Modules\Scheduling\Domain\SchedulingDomainEvent;

/**
 * Define the infrastructure boundary used to publish committed scheduling events.
 */
interface DomainEventPublisher
{
    /**
     * Publish one immutable scheduling event.
     */
    public function publish(SchedulingDomainEvent $event): void;
}
