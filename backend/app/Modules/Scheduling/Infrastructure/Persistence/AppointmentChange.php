<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Scheduling\Domain\AppointmentCancellationReason;
use App\Modules\Scheduling\Domain\AppointmentChangeTransition;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Preserve an immutable, actor-attributed appointment transition.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $appointment_id
 * @property int $identity_account_id
 * @property string $public_id
 * @property AppointmentChangeTransition $transition
 * @property AppointmentStatus $from_status
 * @property AppointmentStatus $to_status
 * @property int $from_slot_id
 * @property int|null $to_slot_id
 * @property AppointmentCancellationReason|null $reason_code
 * @property int $from_version
 * @property int $to_version
 * @property string $request_public_id
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $created_at
 * @property-read Appointment $appointment
 * @property-read IdentityAccount $actor
 * @property-read AppointmentSlot $fromSlot
 * @property-read AppointmentSlot|null $toSlot
 */
#[Fillable([
    'organization_id',
    'appointment_id',
    'identity_account_id',
    'public_id',
    'transition',
    'from_status',
    'to_status',
    'from_slot_id',
    'to_slot_id',
    'reason_code',
    'from_version',
    'to_version',
    'request_public_id',
    'occurred_at',
])]
#[Hidden([
    'id',
    'organization_id',
    'appointment_id',
    'identity_account_id',
    'from_slot_id',
    'to_slot_id',
    'request_public_id',
])]
final class AppointmentChange extends Model
{
    use HasPublicId;

    public $timestamps = false;

    /**
     * Get the appointment whose state changed.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the authenticated identity that initiated the transition.
     *
     * @return BelongsTo<IdentityAccount, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(IdentityAccount::class, 'identity_account_id');
    }

    /**
     * Get the slot owned before the transition.
     *
     * @return BelongsTo<AppointmentSlot, $this>
     */
    public function fromSlot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'from_slot_id');
    }

    /**
     * Get the destination slot, when the transition was a reschedule.
     *
     * @return BelongsTo<AppointmentSlot, $this>
     */
    public function toSlot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'to_slot_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transition' => AppointmentChangeTransition::class,
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
            'reason_code' => AppointmentCancellationReason::class,
            'from_version' => 'integer',
            'to_version' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Register guards that keep the appointment timeline append-only.
     */
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Appointment changes are append-only and cannot be updated.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Appointment changes are append-only and cannot be deleted.');
        });
    }
}
