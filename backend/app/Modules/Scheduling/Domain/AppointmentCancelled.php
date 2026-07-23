<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Carry the minimum non-clinical data required by downstream scheduling handlers.
 */
final readonly class AppointmentCancelled implements SchedulingDomainEvent
{
    public function __construct(
        public string $appointmentId,
        public string $organizationId,
        public string $actorIdentityId,
        public AppointmentCancellationReason $reason,
        public DateTimeImmutable $occurredAt,
    ) {}
}
