<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persist only keyed lookup material and an Argon2id recovery code hash.
 *
 * @property int $id
 * @property int $identity_mfa_method_id
 * @property string $public_id
 * @property string $lookup_digest
 * @property string $code_hash
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable $created_at
 * @property-read IdentityMfaMethod $method
 */
#[Guarded(['*'])]
#[Hidden([
    'id',
    'identity_mfa_method_id',
    'lookup_digest',
    'code_hash',
])]
final class IdentityRecoveryCode extends Model
{
    use HasPublicId;

    public $timestamps = false;

    /**
     * Get the MFA method that owns this recovery code.
     *
     * @return BelongsTo<IdentityMfaMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(IdentityMfaMethod::class, 'identity_mfa_method_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
