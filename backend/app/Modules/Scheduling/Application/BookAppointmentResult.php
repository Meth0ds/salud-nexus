<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;

/**
 * Return a booked appointment together with its idempotency replay state.
 */
final readonly class BookAppointmentResult
{
    public function __construct(
        public Appointment $appointment,
        public bool $replayed,
    ) {}
}
