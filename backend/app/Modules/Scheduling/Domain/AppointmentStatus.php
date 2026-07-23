<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

/**
 * Represent the supported lifecycle states of a patient appointment.
 */
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';
}
