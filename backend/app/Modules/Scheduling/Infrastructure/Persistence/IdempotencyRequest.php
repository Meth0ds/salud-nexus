<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist one actor- and operation-scoped idempotent command lifecycle.
 *
 * Sensitive keys and hashes remain hidden from serialized model output.
 *
 * @property int $id
 * @property int $identity_account_id
 * @property string $route
 * @property string $idempotency_key
 * @property string $request_hash
 * @property string $status
 * @property int|null $response_status
 * @property string|null $resource_public_id
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $expires_at
 */
#[Fillable([
    'identity_account_id',
    'route',
    'idempotency_key',
    'request_hash',
    'status',
    'response_status',
    'resource_public_id',
    'completed_at',
    'expires_at',
])]
#[Hidden(['id', 'identity_account_id', 'idempotency_key', 'request_hash'])]
final class IdempotencyRequest extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
