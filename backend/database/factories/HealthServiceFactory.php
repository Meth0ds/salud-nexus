<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Scheduling\Infrastructure\Persistence\HealthService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Build active health-service fixtures for scheduling tests.
 *
 * @extends Factory<HealthService>
 */
final class HealthServiceFactory extends Factory
{
    protected $model = HealthService::class;

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
            'code' => 'SRV-'.fake()->unique()->numerify('######'),
            'name' => fake()->randomElement([
                'Medicina general',
                'Enfermeria',
                'Fisioterapia',
            ]).' '.fake()->unique()->numerify('###'),
            'is_active' => true,
        ];
    }
}
