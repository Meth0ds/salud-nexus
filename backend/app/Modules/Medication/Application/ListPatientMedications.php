<?php

declare(strict_types=1);

namespace App\Modules\Medication\Application;

use App\Modules\Medication\Infrastructure\Persistence\Medication;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use Illuminate\Database\Eloquent\Collection;

/**
 * List the medication summary visible to the authenticated patient.
 */
final readonly class ListPatientMedications
{
    /**
     * Return owned medications with their pending renewal state.
     *
     * @return Collection<int, Medication>
     */
    public function handle(Patient $patient): Collection
    {
        return Medication::query()
            ->with('pendingRenewal')
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }
}
