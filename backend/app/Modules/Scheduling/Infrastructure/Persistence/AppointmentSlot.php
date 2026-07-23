<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Scheduling\Domain\SlotStatus;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

/**
 * Persist one center-local capacity window for an appointment type.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $center_id
 * @property int $appointment_type_id
 * @property string $public_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property SlotStatus $status
 * @property string|null $location_label
 * @property string|null $professional_display_name
 * @property-read Center $center
 * @property-read AppointmentType $appointmentType
 * @property-read AppointmentSlotAllocation|null $activeAllocation
 */
#[Fillable([
    'organization_id',
    'center_id',
    'appointment_type_id',
    'public_id',
    'starts_at',
    'ends_at',
    'status',
    'location_label',
    'professional_display_name',
])]
#[Hidden(['id', 'organization_id', 'center_id', 'appointment_type_id'])]
final class AppointmentSlot extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<AppointmentSlotFactory>
     */
    use HasFactory, HasPublicId;

    /**
     * Get the center that owns the slot.
     *
     * @return BelongsTo<Center, $this>
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Get the appointment type offered in this slot.
     *
     * @return BelongsTo<AppointmentType, $this>
     */
    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    /**
     * Get appointment snapshots that have referenced the slot over time.
     *
     * @return HasMany<Appointment, $this>
     */
    public function historicalAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'slot_id');
    }

    /**
     * Get the appointment that currently owns the slot, when allocated.
     *
     * @return HasOne<AppointmentSlotAllocation, $this>
     */
    public function activeAllocation(): HasOne
    {
        return $this->hasOne(AppointmentSlotAllocation::class, 'slot_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => SlotStatus::class,
        ];
    }

    /**
     * Register the positive-duration invariant enforced for every model save.
     */
    protected static function booted(): void
    {
        self::saving(static function (self $slot): void {
            if ($slot->ends_at <= $slot->starts_at) {
                throw new InvalidArgumentException('Appointment slot must end after it starts.');
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AppointmentSlotFactory
    {
        return AppointmentSlotFactory::new();
    }
}
