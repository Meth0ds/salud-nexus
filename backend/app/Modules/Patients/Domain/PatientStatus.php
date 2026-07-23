<?php

declare(strict_types=1);

namespace App\Modules\Patients\Domain;

/**
 * Represent whether a patient record is eligible for portal operations.
 */
enum PatientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deceased = 'deceased';
}
