<?php

declare(strict_types=1);

namespace App\Modules\Medication\Infrastructure\Persistence;

use App\Modules\Medication\Domain\RenewalRequestStatus;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persist the workflow state of one patient medication renewal request.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $patient_id
 * @property int $medication_id
 * @property string $public_id
 * @property RenewalRequestStatus $status
 * @property CarbonImmutable $requested_at
 * @property-read Medication $medication
 */
#[Fillable([
    'organization_id',
    'patient_id',
    'medication_id',
    'public_id',
    'status',
    'requested_at',
])]
#[Hidden(['id', 'organization_id', 'patient_id', 'medication_id'])]
final class MedicationRenewalRequest extends Model
{
    use HasPublicId;

    /**
     * Get the professional medication record being renewed.
     *
     * @return BelongsTo<Medication, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RenewalRequestStatus::class,
            'requested_at' => 'immutable_datetime',
        ];
    }
}
