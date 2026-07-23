<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Patients\Domain\PatientStatus;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Build active patient fixtures with non-sensitive synthetic demographics.
 *
 * @extends Factory<Patient>
 */
final class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'home_center_id' => null,
            'public_id' => Str::uuid7()->toString(),
            'record_number' => 'PAC-'.fake()->unique()->numerify('########'),
            'display_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'status' => PatientStatus::Active,
        ];
    }
}
