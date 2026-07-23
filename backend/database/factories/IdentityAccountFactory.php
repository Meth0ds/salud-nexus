<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\IdentityAccountStatus;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Build active identity accounts with securely hashed test credentials.
 *
 * @extends Factory<IdentityAccount>
 */
final class IdentityAccountFactory extends Factory
{
    protected $model = IdentityAccount::class;

    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => Str::uuid7()->toString(),
            'display_name' => fake()->name(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'email_verified_at' => now(),
            'status' => IdentityAccountStatus::Active,
            'password' => self::$password ??= Hash::make('local-development-password'),
            'remember_token' => null,
        ];
    }

    /**
     * Mark the generated identity account as temporarily suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => IdentityAccountStatus::Suspended,
        ]);
    }

    /**
     * Mark the generated identity account as disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'status' => IdentityAccountStatus::Disabled,
        ]);
    }
}
