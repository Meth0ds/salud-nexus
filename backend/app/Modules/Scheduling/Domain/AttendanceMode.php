<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

/**
 * Describe how a patient attends an appointment.
 */
enum AttendanceMode: string
{
    case InPerson = 'in_person';
    case Video = 'video';
    case Phone = 'phone';
}
