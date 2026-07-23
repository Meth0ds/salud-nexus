<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Events;

use App\Modules\Scheduling\Application\Ports\DomainEventPublisher;
use App\Modules\Scheduling\Domain\SchedulingDomainEvent;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Publish scheduling domain events through Laravel's event dispatcher.
 */
final readonly class LaravelDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(private Dispatcher $events) {}

    /**
     * Dispatch a committed scheduling event to synchronous and queued listeners.
     */
    public function publish(SchedulingDomainEvent $event): void
    {
        $this->events->dispatch($event);
    }
}
