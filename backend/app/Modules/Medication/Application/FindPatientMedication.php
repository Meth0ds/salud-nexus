<?php

declare(strict_types=1);

namespace App\Modules\Medication\Application;

use App\Modules\Medication\Infrastructure\Persistence\Medication;
use App\Modules\Patients\Infrastructure\Persistence\Patient;

/**
 * Resolve one medication through mandatory organization and patient scopes.
 */
final readonly class FindPatientMedication
{
    /**
     * Return the owned medication or an indistinguishable not-found result.
     */
    public function handle(Patient $patient, string $publicId): Medication
    {
        return Medication::query()
            ->with('pendingRenewal')
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', strtolower($publicId))
            ->firstOrFail();
    }
}
