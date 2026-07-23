<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Allow only explicitly reviewed purposes to request MFA step-up.
 */
enum MfaStepUpPurpose: string
{
    case AccountSecurityChange = 'account_security_change';
    case ClinicalDocumentDownload = 'clinical_document_download';
    case PatientDataExport = 'patient_data_export';
    case StaffAccessChange = 'staff_access_change';
}
