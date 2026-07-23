<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Resources;

use App\Modules\Organizations\Infrastructure\Persistence\Center;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present the single center and its canonical IANA timezone.
 *
 * @mixin Center
 */
final class CenterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: string, name: string, timezone: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'timezone' => $this->timezone,
        ];
    }
}
