<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Organizations\Domain\OrganizationStatus;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Build the active organization that owns the healthcare center.
 *
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => Str::uuid7()->toString(),
            'code' => 'ORG-'.fake()->unique()->numerify('######'),
            'name' => 'Organizacion '.fake()->unique()->company(),
            'status' => OrganizationStatus::Active,
        ];
    }
}
