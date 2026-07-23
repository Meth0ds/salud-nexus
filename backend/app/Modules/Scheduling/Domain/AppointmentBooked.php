<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Carry the minimum non-clinical data required after a booking commits.
 */
final readonly class AppointmentBooked implements SchedulingDomainEvent
{
    public function __construct(
        public string $appointmentId,
        public string $organizationId,
        public string $actorIdentityId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
