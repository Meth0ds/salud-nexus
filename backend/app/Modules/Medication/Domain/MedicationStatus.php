<?php

declare(strict_types=1);

namespace App\Modules\Medication\Domain;

/**
 * Represent whether a medication remains active in the patient summary.
 */
enum MedicationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
