<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Domain\AttendanceMode;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Persist the current patient-facing snapshot of a scheduled encounter.
 *
 * Active slot ownership is represented by AppointmentSlotAllocation; the slot
 * reference retained here also forms part of the immutable historical snapshot.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $patient_id
 * @property int $center_id
 * @property int $appointment_type_id
 * @property int $slot_id
 * @property string $public_id
 * @property AppointmentStatus $status
 * @property int $version
 * @property AttendanceMode $attendance_mode
 * @property string $center_timezone
 * @property string|null $location_label
 * @property string|null $professional_display_name
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property-read Center $center
 * @property-read AppointmentType $appointmentType
 * @property-read Patient $patient
 * @property-read AppointmentSlotAllocation|null $activeSlotAllocation
 */
#[Fillable([
    'organization_id',
    'patient_id',
    'center_id',
    'appointment_type_id',
    'slot_id',
    'public_id',
    'status',
    'attendance_mode',
    'center_timezone',
    'location_label',
    'professional_display_name',
    'starts_at',
    'ends_at',
])]
#[Hidden([
    'id',
    'organization_id',
    'patient_id',
    'center_id',
    'appointment_type_id',
    'slot_id',
])]
final class Appointment extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<AppointmentFactory>
     */
    use HasFactory, HasPublicId;

    /**
     * Get the patient who owns the appointment.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the single center at which the appointment is delivered.
     *
     * @return BelongsTo<Center, $this>
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Get the appointment type captured by this booking.
     *
     * @return BelongsTo<AppointmentType, $this>
     */
    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    /**
     * Get the slot referenced by the current appointment snapshot.
     *
     * @return BelongsTo<AppointmentSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'slot_id');
    }

    /**
     * Get the live slot allocation, when the appointment still owns one.
     *
     * @return HasOne<AppointmentSlotAllocation, $this>
     */
    public function activeSlotAllocation(): HasOne
    {
        return $this->hasOne(AppointmentSlotAllocation::class);
    }

    /**
     * Get the append-only transition timeline in occurrence order.
     *
     * @return HasMany<AppointmentChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(AppointmentChange::class)->orderBy('occurred_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'version' => 'integer',
            'attendance_mode' => AttendanceMode::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }
}
