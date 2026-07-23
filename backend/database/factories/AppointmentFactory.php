<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Domain\AttendanceMode;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlotAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Build appointment fixtures that preserve production slot-ownership rules.
 *
 * @extends Factory<Appointment>
 */
final class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * Keep test fixtures faithful to the production slot-ownership invariant.
     */
    public function configure(): static
    {
        return $this->afterCreating(static function (Appointment $appointment): void {
            if (
                $appointment->status !== AppointmentStatus::Scheduled
                || ! Schema::hasTable('appointment_slot_allocations')
            ) {
                return;
            }

            AppointmentSlotAllocation::query()->create([
                'organization_id' => $appointment->organization_id,
                'appointment_id' => $appointment->id,
                'slot_id' => $appointment->slot_id,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now('UTC')->addDays(10)->setTime(9, 0);

        return [
            'organization_id' => OrganizationFactory::new(),
            'slot_id' => static fn (array $attributes): int => AppointmentSlot::factory()->create([
                'organization_id' => $attributes['organization_id'],
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes(30),
            ])->id,
            'patient_id' => static fn (array $attributes): int => Patient::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'center_id' => fn (array $attributes): int => $this->slotFor($attributes)->center_id,
            'appointment_type_id' => fn (array $attributes): int => $this->slotFor($attributes)->appointment_type_id,
            'public_id' => Str::uuid7()->toString(),
            'status' => AppointmentStatus::Scheduled,
            'attendance_mode' => AttendanceMode::InPerson,
            'center_timezone' => 'Europe/Madrid',
            'location_label' => 'Consulta 1',
            'professional_display_name' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
        ];
    }

    /**
     * Resolve the persisted slot used by dependent factory attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function slotFor(array $attributes): AppointmentSlot
    {
        $slotId = $attributes['slot_id'] ?? null;

        if (! is_int($slotId)) {
            throw new \LogicException('Appointment factories require a persisted slot.');
        }

        return AppointmentSlot::query()->whereKey($slotId)->firstOrFail();
    }
}
