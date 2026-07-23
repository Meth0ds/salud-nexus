<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Resources;

use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present one bookable appointment type with its available slot collection.
 *
 * @mixin AppointmentType
 */
final class BookingAppointmentTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'duration_minutes' => $this->duration_minutes,
            'attendance_mode' => $this->attendance_mode->value,
            'service' => [
                'id' => $this->healthService->public_id,
                'name' => $this->healthService->name,
            ],
            'slots' => $this->slots
                ->map(static fn ($slot): array => (new BookingSlotResource($slot))->toArray($request))
                ->values()
                ->all(),
        ];
    }
}
