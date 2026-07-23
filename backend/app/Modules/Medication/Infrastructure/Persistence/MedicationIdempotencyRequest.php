<?php

declare(strict_types=1);

namespace App\Modules\Medication\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist a hashed, actor-scoped medication command lifecycle.
 *
 * @property int $id
 * @property int $identity_account_id
 * @property string $operation
 * @property string $idempotency_key_hash
 * @property string $request_hash
 * @property string $status
 * @property string|null $resource_type
 * @property string|null $resource_public_id
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $expires_at
 */
#[Fillable([
    'identity_account_id',
    'operation',
    'idempotency_key_hash',
    'request_hash',
    'status',
    'resource_type',
    'resource_public_id',
    'completed_at',
    'expires_at',
])]
#[Hidden(['id', 'identity_account_id', 'idempotency_key_hash', 'request_hash'])]
final class MedicationIdempotencyRequest extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
