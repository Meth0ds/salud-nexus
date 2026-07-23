<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Scheduling\Domain\SlotStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Build future appointment-slot fixtures for the single center.
 *
 * @extends Factory<AppointmentSlot>
 */
final class AppointmentSlotFactory extends Factory
{
    protected $model = AppointmentSlot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now('UTC')
            ->addDays(fake()->unique()->numberBetween(2, 365))
            ->setTime(fake()->numberBetween(7, 18), 0);

        return [
            'organization_id' => OrganizationFactory::new(),
            'center_id' => static fn (array $attributes): int => Center::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'appointment_type_id' => static fn (array $attributes): int => AppointmentType::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'public_id' => Str::uuid7()->toString(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'status' => SlotStatus::Open,
            'location_label' => 'Consulta '.fake()->numberBetween(1, 20),
            'professional_display_name' => null,
        ];
    }

    /**
     * Mark the generated slot as unavailable for booking.
     */
    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => SlotStatus::Blocked]);
    }
}
