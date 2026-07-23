<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

/**
 * Supported patient-facing transitions for an existing appointment.
 */
enum AppointmentChangeTransition: string
{
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
}
