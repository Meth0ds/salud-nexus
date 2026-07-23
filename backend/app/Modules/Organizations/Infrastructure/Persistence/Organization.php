<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Infrastructure\Persistence;

use App\Modules\Organizations\Domain\OrganizationStatus;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Persist the legal organization that owns the single healthcare center.
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property string $name
 * @property OrganizationStatus $status
 */
#[Fillable(['public_id', 'code', 'name', 'status'])]
#[Hidden(['id'])]
final class Organization extends Model
{
    /**
     * Enable model factories and public UUID route keys.
     *
     * @use HasFactory<OrganizationFactory>
     */
    use HasFactory, HasPublicId;

    /**
     * Get the healthcare center operated by the organization.
     *
     * @return HasOne<Center, $this>
     */
    public function center(): HasOne
    {
        return $this->hasOne(Center::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => OrganizationStatus::class];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
