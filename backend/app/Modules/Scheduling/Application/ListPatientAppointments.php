<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\Clock\ClockInterface;

/**
 * List appointments through mandatory organization and patient scopes.
 */
final readonly class ListPatientAppointments
{
    public function __construct(private ClockInterface $clock) {}

    /**
     * Return a stable, bounded page for the requested lifecycle scope.
     *
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function handle(Patient $patient, string $scope, int $perPage): LengthAwarePaginator
    {
        $now = $this->clock->now();
        $query = Appointment::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->with(['center', 'appointmentType.healthService'])
            ->orderBy('starts_at')
            ->orderBy('id');

        if ($scope === 'upcoming') {
            $query->where('status', AppointmentStatus::Scheduled->value)
                ->where('starts_at', '>=', $now);
        } elseif ($scope === 'past') {
            $query->where(static function ($past) use ($now): void {
                $past->where('status', '!=', AppointmentStatus::Scheduled->value)
                    ->orWhere('starts_at', '<', $now);
            });
        }

        return $query->paginate($perPage);
    }
}
