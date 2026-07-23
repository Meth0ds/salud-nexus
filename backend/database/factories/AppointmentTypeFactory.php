<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Scheduling\Domain\AttendanceMode;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use App\Modules\Scheduling\Infrastructure\Persistence\HealthService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Build active appointment-type fixtures with valid duration defaults.
 *
 * @extends Factory<AppointmentType>
 */
final class AppointmentTypeFactory extends Factory
{
    protected $model = AppointmentType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'health_service_id' => static fn (array $attributes): int => HealthService::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'public_id' => Str::uuid7()->toString(),
            'code' => 'TYP-'.fake()->unique()->numerify('######'),
            'name' => 'Consulta '.fake()->unique()->numerify('####'),
            'duration_minutes' => 30,
            'attendance_mode' => AttendanceMode::InPerson,
            'is_active' => true,
        ];
    }
}
