<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Organizations\Domain\CenterStatus;
use App\Modules\Organizations\Infrastructure\Persistence\Center;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Build fixtures for the application's single healthcare center.
 *
 * @extends Factory<Center>
 */
final class CenterFactory extends Factory
{
    protected $model = Center::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'public_id' => Str::uuid7()->toString(),
            'code' => 'CTR-'.fake()->unique()->numerify('######'),
            'name' => 'Centro '.fake()->unique()->city(),
            'timezone' => 'Europe/Madrid',
            'status' => CenterStatus::Active,
        ];
    }
}
