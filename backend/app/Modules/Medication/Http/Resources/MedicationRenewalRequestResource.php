<?php

declare(strict_types=1);

namespace App\Modules\Medication\Http\Resources;

use App\Modules\Medication\Infrastructure\Persistence\MedicationRenewalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present the patient-visible state of a medication renewal request.
 *
 * @mixin MedicationRenewalRequest
 */
final class MedicationRenewalRequestResource extends JsonResource
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
            'medication_id' => $this->medication->public_id,
            'status' => $this->status->value,
            'requested_at' => $this->requested_at->utc()->format(DATE_ATOM),
        ];
    }
}
