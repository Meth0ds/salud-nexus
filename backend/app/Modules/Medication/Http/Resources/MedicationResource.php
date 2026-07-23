<?php

declare(strict_types=1);

namespace App\Modules\Medication\Http\Resources;

use App\Modules\Medication\Domain\MedicationSource;
use App\Modules\Medication\Domain\MedicationStatus;
use App\Modules\Medication\Infrastructure\Persistence\Medication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present a medication summary without exposing internal clinical metadata.
 *
 * @mixin Medication
 */
final class MedicationResource extends JsonResource
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
            'source' => $this->source->value,
            'name' => $this->name,
            'presentation' => $this->presentation,
            'schedule_label' => $this->schedule_label,
            'status' => $this->status->value,
            'can_request_renewal' => $this->source === MedicationSource::ProfessionalRecord
                && $this->status === MedicationStatus::Active
                && $this->pendingRenewal === null,
            'renewal_request_status' => $this->pendingRenewal?->status->value,
            'updated_at' => $this->updated_at->utc()->format(DATE_ATOM),
        ];
    }
}
