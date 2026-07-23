<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Assign UUIDv7 route keys to Eloquent models that expose public identifiers.
 */
trait HasPublicId
{
    /**
     * Register the model creation hook that supplies a missing public ID.
     */
    protected static function bootHasPublicId(): void
    {
        static::creating(static function (Model $model): void {
            $publicId = $model->getAttribute('public_id');

            if (! is_string($publicId) || $publicId === '') {
                $model->setAttribute('public_id', Str::uuid7()->toString());
            }
        });
    }

    /**
     * Use the non-enumerable public identifier for implicit route binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
