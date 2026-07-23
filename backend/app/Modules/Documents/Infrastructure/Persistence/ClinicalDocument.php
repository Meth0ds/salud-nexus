<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\Persistence;

use App\Modules\Documents\Domain\DocumentCategory;
use App\Modules\Documents\Domain\DocumentStatus;
use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ClinicalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Persist a patient-owned document aggregate without embedding file contents.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $patient_id
 * @property int $center_id
 * @property string $public_id
 * @property string $title
 * @property DocumentCategory $category
 * @property DocumentStatus $status
 * @property CarbonImmutable|null $retention_until
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Center $center
 * @property-read DocumentPublication|null $activePublication
 */
#[Fillable([
    'organization_id',
    'patient_id',
    'center_id',
    'public_id',
    'title',
    'category',
    'status',
    'retention_until',
])]
#[Hidden(['id', 'organization_id', 'patient_id', 'center_id'])]
final class ClinicalDocument extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<ClinicalDocumentFactory>
     */
    use HasFactory, HasPublicId;

    protected $table = 'documents';

    /**
     * Get the organization that owns the document.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the patient permitted to access the document.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the center that issued the document.
     *
     * @return BelongsTo<Center, $this>
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Get the append-only file versions of the document.
     *
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id');
    }

    /**
     * Get the latest publication that remains visible to the patient.
     *
     * @return HasOne<DocumentPublication, $this>
     */
    public function activePublication(): HasOne
    {
        return $this->hasOne(DocumentPublication::class, 'document_id')
            ->whereNull('withdrawn_at')
            ->where('published_at', '<=', now())
            ->latestOfMany('published_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'status' => DocumentStatus::class,
            'retention_until' => 'immutable_datetime',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ClinicalDocumentFactory
    {
        return ClinicalDocumentFactory::new();
    }
}
