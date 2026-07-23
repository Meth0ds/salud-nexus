<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Resources;

use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present a slot with both canonical UTC and center-local timestamps.
 *
 * @mixin AppointmentSlot
 */
final class BookingSlotResource extends JsonResource
{
    /**
     * Transform the resource into an array without exposing allocation state.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = $this->center->timezone;

        return [
            'id' => $this->public_id,
            'starts_at' => $this->starts_at->utc()->format(DATE_ATOM),
            'ends_at' => $this->ends_at->utc()->format(DATE_ATOM),
            'local_starts_at' => $this->starts_at->setTimezone($timezone)->format(DATE_ATOM),
            'local_ends_at' => $this->ends_at->setTimezone($timezone)->format(DATE_ATOM),
            'center' => (new CenterResource($this->center))->toArray($request),
            'location_label' => $this->location_label,
        ];
    }
}
