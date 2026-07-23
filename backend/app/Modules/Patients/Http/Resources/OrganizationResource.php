<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Resources;

use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present the organization without exposing internal database identifiers.
 *
 * @mixin Organization
 */
final class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: string, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
        ];
    }
}
