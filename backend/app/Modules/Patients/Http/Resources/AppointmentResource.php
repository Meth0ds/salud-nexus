<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Resources;

use App\Modules\Scheduling\Application\PatientAppointmentChangePolicy;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Present an appointment without exposing internal keys or clinical data.
 *
 * @mixin Appointment
 */
final class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into the stable patient-facing representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $appointment = $this->resource;

        if (! $appointment instanceof Appointment) {
            throw new LogicException('An appointment resource requires an appointment model.');
        }

        $changePolicy = app(PatientAppointmentChangePolicy::class);

        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'version' => $this->version,
            'change_allowed' => $changePolicy->allows($appointment),
            'change_deadline' => $changePolicy->deadline($appointment)->utc()->format(DATE_ATOM),
            'attendance_mode' => $this->attendance_mode->value,
            'location_label' => $this->location_label,
            'professional_display_name' => $this->professional_display_name,
            'service' => [
                'id' => $this->appointmentType->healthService->public_id,
                'name' => $this->appointmentType->healthService->name,
            ],
            'appointment_type' => [
                'id' => $this->appointmentType->public_id,
                'name' => $this->appointmentType->name,
                'duration_minutes' => $this->appointmentType->duration_minutes,
            ],
            'center' => (new CenterResource($this->center))->toArray($request),
            'starts_at' => $this->starts_at->utc()->format(DATE_ATOM),
            'local_starts_at' => $this->starts_at->setTimezone($this->center_timezone)->format(DATE_ATOM),
            'ends_at' => $this->ends_at->utc()->format(DATE_ATOM),
            'local_ends_at' => $this->ends_at->setTimezone($this->center_timezone)->format(DATE_ATOM),
        ];
    }
}
