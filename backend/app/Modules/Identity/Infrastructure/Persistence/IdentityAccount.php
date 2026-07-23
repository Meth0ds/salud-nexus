<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\IdentityAccountStatus;
use Carbon\CarbonImmutable;
use Database\Factories\IdentityAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Persist an authentication identity separately from patient domain records.
 *
 * @property int $id
 * @property string $public_id
 * @property string $display_name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property IdentityAccountStatus $status
 * @property string $password
 * @property string|null $remember_token
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, IdentityMfaMethod> $mfaMethods
 */
#[Fillable(['display_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
final class IdentityAccount extends Authenticatable
{
    /**
     * Enable model factories and Laravel notifications.
     *
     * @use HasFactory<IdentityAccountFactory>
     */
    use HasFactory, Notifiable;

    protected $table = 'identity_accounts';

    /**
     * Get the MFA methods registered for this identity.
     *
     * @return HasMany<IdentityMfaMethod, $this>
     */
    public function mfaMethods(): HasMany
    {
        return $this->hasMany(IdentityMfaMethod::class, 'identity_account_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'status' => IdentityAccountStatus::class,
        ];
    }

    /**
     * Normalize public IDs and email addresses before account creation.
     */
    protected static function booted(): void
    {
        self::creating(static function (self $account): void {
            $publicId = $account->getAttribute('public_id');

            if (! is_string($publicId) || $publicId === '') {
                $account->public_id = Str::uuid7()->toString();
            }

            $account->email = Str::lower(trim((string) $account->email));
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): IdentityAccountFactory
    {
        return IdentityAccountFactory::new();
    }
}
