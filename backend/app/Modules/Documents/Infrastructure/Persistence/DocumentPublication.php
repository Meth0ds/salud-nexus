<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persist the visibility window for one immutable document version.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $patient_id
 * @property int $document_id
 * @property int $document_version_id
 * @property string $public_id
 * @property CarbonImmutable $published_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property-read DocumentVersion $version
 */
#[Fillable([
    'organization_id',
    'patient_id',
    'document_id',
    'document_version_id',
    'public_id',
    'published_at',
    'withdrawn_at',
])]
#[Hidden(['id', 'organization_id', 'patient_id', 'document_id', 'document_version_id'])]
final class DocumentPublication extends Model
{
    use HasPublicId;

    /**
     * Get the document aggregate being published.
     *
     * @return BelongsTo<ClinicalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ClinicalDocument::class, 'document_id');
    }

    /**
     * Get the exact immutable version visible to the patient.
     *
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
