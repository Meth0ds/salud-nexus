<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Describe an atomic slot movement without embedding patient or clinical data.
 */
final readonly class AppointmentRescheduled implements SchedulingDomainEvent
{
    public function __construct(
        public string $appointmentId,
        public string $organizationId,
        public string $actorIdentityId,
        public string $fromSlotId,
        public string $toSlotId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
