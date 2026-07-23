<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Resources;

use App\Modules\Patients\Infrastructure\Persistence\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present the minimum demographic profile required by the patient portal.
 *
 * @mixin Patient
 */
final class PatientProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     record_number: string,
     *     display_name: string,
     *     date_of_birth: string,
     *     organization: array{id: string, name: string},
     *     home_center: array{id: string, name: string, timezone: string}|null
     * }
     */
    public function toArray(Request $request): array
    {
        $organization = (new OrganizationResource($this->organization))->toArray($request);
        $homeCenter = $this->homeCenter;

        return [
            'id' => $this->public_id,
            'record_number' => $this->record_number,
            'display_name' => $this->display_name,
            'date_of_birth' => $this->date_of_birth->format('Y-m-d'),
            'organization' => $organization,
            'home_center' => $homeCenter === null
                ? null
                : (new CenterResource($homeCenter))->toArray($request),
        ];
    }
}
