<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

/**
 * Patient-selectable reasons deliberately exclude free-form clinical data.
 */
enum AppointmentCancellationReason: string
{
    case PlansChanged = 'plans_changed';
    case FeelingBetter = 'feeling_better';
    case TransportIssue = 'transport_issue';
    case Other = 'other';
}
