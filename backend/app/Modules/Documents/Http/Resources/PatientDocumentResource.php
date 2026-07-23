<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Resources;

use App\Modules\Documents\Infrastructure\Persistence\ClinicalDocument;
use App\Modules\Documents\Infrastructure\Persistence\DocumentPublication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Present published document metadata without exposing storage identifiers.
 *
 * @mixin ClinicalDocument
 */
final class PatientDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $publication = $this->activePublication;
        if (! $publication instanceof DocumentPublication) {
            throw new LogicException('A patient document resource requires an active publication.');
        }
        $version = $publication->version;

        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'category' => $this->category->value,
            'published_at' => $publication->published_at->utc()->format(DATE_ATOM),
            'center' => [
                'id' => $this->center->public_id,
                'name' => $this->center->name,
            ],
            'file' => [
                'mime_type' => $version->mime_type,
                'size_bytes' => $version->byte_size,
                'version' => $version->version_number,
            ],
            'integrity_status' => 'verified',
            'can_download' => true,
        ];
    }
}
