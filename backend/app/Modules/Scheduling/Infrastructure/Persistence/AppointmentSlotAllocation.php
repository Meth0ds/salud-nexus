<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represent the single active owner of an appointment slot.
 *
 * Allocations are created and removed inside the same transaction as the
 * appointment transition. Historical ownership belongs in AppointmentChange.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $appointment_id
 * @property int $slot_id
 * @property CarbonImmutable $created_at
 * @property-read Appointment $appointment
 * @property-read AppointmentSlot $slot
 */
#[Fillable(['organization_id', 'appointment_id', 'slot_id'])]
#[Hidden(['id', 'organization_id', 'appointment_id', 'slot_id'])]
final class AppointmentSlotAllocation extends Model
{
    public $timestamps = false;

    /**
     * Get the appointment that currently owns the slot.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the exclusively allocated slot.
     *
     * @return BelongsTo<AppointmentSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
