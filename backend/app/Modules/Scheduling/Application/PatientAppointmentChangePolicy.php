<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use Carbon\CarbonImmutable;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Enforce appointment lifecycle and cutoff policy inside the trusted boundary.
 */
final readonly class PatientAppointmentChangePolicy
{
    public function __construct(private ClockInterface $clock) {}

    /**
     * Reject a mutation when lifecycle or configured cutoff rules disallow it.
     */
    public function assertAllowed(Appointment $appointment): void
    {
        if (! $this->allows($appointment)) {
            throw new ConflictHttpException;
        }
    }

    /**
     * Determine whether the appointment is currently mutable by the patient.
     */
    public function allows(Appointment $appointment): bool
    {
        return $appointment->status === AppointmentStatus::Scheduled
            && $this->clock->now() <= $this->deadline($appointment);
    }

    /**
     * Calculate the authoritative center-independent cutoff instant.
     */
    public function deadline(Appointment $appointment): CarbonImmutable
    {
        return $appointment->starts_at->subMinutes(
            (int) config('scheduling.patient_change_cutoff_minutes'),
        );
    }
}
