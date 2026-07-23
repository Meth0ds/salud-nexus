<?php

declare(strict_types=1);

namespace App\Modules\Medication\Domain;

/**
 * Distinguish professional medication records from patient declarations.
 */
enum MedicationSource: string
{
    case ProfessionalRecord = 'professional_record';
    case PatientDeclaration = 'patient_declaration';
}
