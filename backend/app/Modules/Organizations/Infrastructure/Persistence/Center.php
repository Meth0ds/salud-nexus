<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Infrastructure\Persistence;

use App\Modules\Organizations\Domain\CenterStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Database\Factories\CenterFactory;
use DateTimeZone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Persist the single healthcare center and its operational timezone.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $public_id
 * @property string $code
 * @property string $name
 * @property string $timezone
 * @property CenterStatus $status
 */
#[Fillable(['organization_id', 'public_id', 'code', 'name', 'timezone', 'status'])]
#[Hidden(['id', 'organization_id'])]
final class Center extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<CenterFactory>
     */
    use HasFactory, HasPublicId;

    /**
     * Get the appointment capacity configured for the center.
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
        return ['status' => CenterStatus::class];
    }

    /**
     * Register the IANA timezone invariant enforced for every model save.
     */
    protected static function booted(): void
    {
        self::saving(static function (self $center): void {
            if (! in_array($center->timezone, DateTimeZone::listIdentifiers(), true)) {
                throw new InvalidArgumentException('Center timezone must be a valid IANA timezone.');
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): CenterFactory
    {
        return CenterFactory::new();
    }
}
