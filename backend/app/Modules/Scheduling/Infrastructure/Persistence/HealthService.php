<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\HasPublicId;
use Database\Factories\HealthServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist a non-clinical service offered by the center.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $public_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'public_id', 'code', 'name', 'is_active'])]
#[Hidden(['id', 'organization_id'])]
final class HealthService extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<HealthServiceFactory>
     */
    use HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): HealthServiceFactory
    {
        return HealthServiceFactory::new();
    }
}
