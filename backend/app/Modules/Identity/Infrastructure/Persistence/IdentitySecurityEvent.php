<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Preserve a minimized append-only authentication security event.
 *
 * @property int $id
 * @property int|null $identity_account_id
 * @property string $public_id
 * @property string $request_public_id
 * @property string $event_type
 * @property string $result
 * @property int $authentication_level
 * @property string $metadata_json
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $created_at
 * @property-read IdentityAccount|null $account
 */
#[Guarded(['*'])]
#[Hidden([
    'id',
    'identity_account_id',
    'metadata_json',
])]
final class IdentitySecurityEvent extends Model
{
    use HasPublicId;

    public $timestamps = false;

    /**
     * Get the identity account associated with this event, when known.
     *
     * @return BelongsTo<IdentityAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(IdentityAccount::class, 'identity_account_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authentication_level' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Register guards that keep identity security events append-only.
     */
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Identity security events are append-only and cannot be updated.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Identity security events are append-only and cannot be deleted.');
        });
    }
}
