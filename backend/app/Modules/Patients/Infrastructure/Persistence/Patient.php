<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence;

use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use App\Modules\Patients\Domain\PatientStatus;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persist the minimum patient identity required by the self-service portal.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $home_center_id
 * @property string $public_id
 * @property string $record_number
 * @property string $display_name
 * @property CarbonImmutable $date_of_birth
 * @property PatientStatus $status
 * @property-read Organization $organization
 * @property-read Center|null $homeCenter
 */
#[Fillable([
    'organization_id',
    'home_center_id',
    'public_id',
    'record_number',
    'display_name',
    'date_of_birth',
    'status',
])]
#[Hidden(['id', 'organization_id', 'home_center_id'])]
final class Patient extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<PatientFactory>
     */
    use HasFactory, HasPublicId;

    /**
     * Get the organization that owns the patient record.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the patient's home center when one is assigned.
     *
     * @return BelongsTo<Center, $this>
     */
    public function homeCenter(): BelongsTo
    {
        return $this->belongsTo(Center::class, 'home_center_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'status' => PatientStatus::class,
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PatientFactory
    {
        return PatientFactory::new();
    }
}
