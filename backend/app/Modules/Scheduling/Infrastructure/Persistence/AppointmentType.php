<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Scheduling\Domain\AttendanceMode;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Database\Factories\AppointmentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Persist a bookable appointment definition for one health service.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $health_service_id
 * @property string $public_id
 * @property string $code
 * @property string $name
 * @property int $duration_minutes
 * @property AttendanceMode $attendance_mode
 * @property bool $is_active
 * @property-read HealthService $healthService
 */
#[Fillable([
    'organization_id',
    'health_service_id',
    'public_id',
    'code',
    'name',
    'duration_minutes',
    'attendance_mode',
    'is_active',
])]
#[Hidden(['id', 'organization_id', 'health_service_id'])]
final class AppointmentType extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<AppointmentTypeFactory>
     */
    use HasFactory, HasPublicId;

    /**
     * Get the health service that owns the appointment type.
     *
     * @return BelongsTo<HealthService, $this>
     */
    public function healthService(): BelongsTo
    {
        return $this->belongsTo(HealthService::class);
    }

    /**
     * Get the slots configured for this appointment type.
     *
     * @return HasMany<AppointmentSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_mode' => AttendanceMode::class,
            'is_active' => 'boolean',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * Register the duration invariant enforced for every model save.
     */
    protected static function booted(): void
    {
        self::saving(static function (self $type): void {
            if ($type->duration_minutes < 5 || $type->duration_minutes > 480) {
                throw new InvalidArgumentException('Appointment duration must be between 5 and 480 minutes.');
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AppointmentTypeFactory
    {
        return AppointmentTypeFactory::new();
    }
}
