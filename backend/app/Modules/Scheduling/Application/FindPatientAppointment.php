<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;

/**
 * Resolve one appointment through organization and patient ownership scopes.
 */
final readonly class FindPatientAppointment
{
    /**
     * Return the owned appointment or Laravel's indistinguishable not-found result.
     */
    public function handle(Patient $patient, string $publicId): Appointment
    {
        return Appointment::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', $publicId)
            ->with(['center', 'appointmentType.healthService'])
            ->firstOrFail();
    }
}
