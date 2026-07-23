<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Domain\SlotStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use Symfony\Component\Clock\ClockInterface;

/**
 * Build the scheduling summary displayed on the patient dashboard.
 */
final readonly class GetPatientDashboard
{
    public function __construct(private ClockInterface $clock) {}

    /**
     * Return bounded counters and the next owned appointment.
     *
     * @return array{
     *     upcoming_appointments_count: int,
     *     next_appointment: Appointment|null,
     *     available_appointment_types_count: int
     * }
     */
    public function handle(Patient $patient): array
    {
        $now = $this->clock->now();
        $appointments = Appointment::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where('starts_at', '>=', $now);

        $next = (clone $appointments)
            ->with(['center', 'appointmentType.healthService'])
            ->orderBy('starts_at')
            ->first();

        $availableTypes = AppointmentType::query()
            ->where('organization_id', $patient->organization_id)
            ->where('is_active', true)
            ->whereHas('healthService', static fn ($query) => $query->where('is_active', true))
            ->whereHas('slots', static function ($query) use ($now): void {
                $query->where('status', SlotStatus::Open->value)
                    ->where('starts_at', '>=', $now)
                    ->whereDoesntHave('activeAllocation')
                    ->whereHas('center', static fn ($centerQuery) => $centerQuery->where('status', 'active'));
            })
            ->count();

        return [
            'upcoming_appointments_count' => (clone $appointments)->count(),
            'next_appointment' => $next,
            'available_appointment_types_count' => $availableTypes,
        ];
    }
}
