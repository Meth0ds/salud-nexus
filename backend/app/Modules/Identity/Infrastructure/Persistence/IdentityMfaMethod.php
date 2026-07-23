<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persist an encrypted MFA secret and its replay-prevention state.
 *
 * @property int $id
 * @property int $identity_account_id
 * @property string $public_id
 * @property MfaMethodType $type
 * @property MfaMethodStatus $status
 * @property string $secret
 * @property int|null $last_used_step
 * @property CarbonImmutable|null $enrollment_expires_at
 * @property CarbonImmutable|null $secret_revealed_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $disabled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read IdentityAccount $account
 * @property-read Collection<int, IdentityRecoveryCode> $recoveryCodes
 */
#[Guarded(['*'])]
#[Hidden([
    'id',
    'identity_account_id',
    'secret',
    'last_used_step',
])]
final class IdentityMfaMethod extends Model
{
    use HasPublicId;

    /**
     * Get the identity account that owns this authenticator.
     *
     * @return BelongsTo<IdentityAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(IdentityAccount::class, 'identity_account_id');
    }

    /**
     * Get the one-use recovery codes issued for this authenticator.
     *
     * @return HasMany<IdentityRecoveryCode, $this>
     */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(IdentityRecoveryCode::class, 'identity_mfa_method_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MfaMethodType::class,
            'status' => MfaMethodStatus::class,
            'secret' => 'encrypted',
            'last_used_step' => 'integer',
            'enrollment_expires_at' => 'immutable_datetime',
            'secret_revealed_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
