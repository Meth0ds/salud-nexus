<?php

declare(strict_types=1);

namespace App\Modules\Medication\Infrastructure\Persistence;

use App\Modules\Medication\Domain\MedicationSource;
use App\Modules\Medication\Domain\MedicationStatus;
use App\Modules\Medication\Domain\RenewalRequestStatus;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\MedicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Persist a patient-visible medication summary and its source attribution.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $patient_id
 * @property string $public_id
 * @property MedicationSource $source
 * @property string $name
 * @property string|null $presentation
 * @property string $schedule_label
 * @property MedicationStatus $status
 * @property string|null $recorded_by_identity_public_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read MedicationRenewalRequest|null $pendingRenewal
 */
#[Fillable([
    'organization_id',
    'patient_id',
    'public_id',
    'source',
    'name',
    'presentation',
    'schedule_label',
    'status',
    'recorded_by_identity_public_id',
])]
#[Hidden(['id', 'organization_id', 'patient_id', 'recorded_by_identity_public_id'])]
final class Medication extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<MedicationFactory>
     */
    use HasFactory, HasPublicId;

    /**
     * Get the organization that owns the medication record.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the patient who owns the medication record.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the latest renewal request that is still pending.
     *
     * @return HasOne<MedicationRenewalRequest, $this>
     */
    public function pendingRenewal(): HasOne
    {
        return $this->hasOne(MedicationRenewalRequest::class)
            ->where('status', RenewalRequestStatus::Submitted->value)
            ->latestOfMany('requested_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => MedicationSource::class,
            'status' => MedicationStatus::class,
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): MedicationFactory
    {
        return MedicationFactory::new();
    }
}
