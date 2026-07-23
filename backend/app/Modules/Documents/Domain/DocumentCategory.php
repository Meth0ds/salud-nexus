<?php

declare(strict_types=1);

namespace App\Modules\Documents\Domain;

/**
 * Enumerate the document categories visible in the patient portal.
 */
enum DocumentCategory: string
{
    case AttendanceCertificate = 'attendance_certificate';
    case CareSummary = 'care_summary';
    case Consent = 'consent';
    case Laboratory = 'laboratory';
    case MedicationSummary = 'medication_summary';
}
