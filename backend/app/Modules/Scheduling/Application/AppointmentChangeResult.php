<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;

/**
 * Return the appointment representation together with replay metadata.
 */
final readonly class AppointmentChangeResult
{
    public function __construct(
        public Appointment $appointment,
        public bool $replayed,
        public ?string $previousSlotPublicId = null,
    ) {}
}
