<?php

declare(strict_types=1);

namespace App\Modules\Medication\Domain;

/**
 * Represent the lifecycle of a medication renewal request.
 */
enum RenewalRequestStatus: string
{
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
