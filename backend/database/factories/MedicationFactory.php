<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Medication\Domain\MedicationSource;
use App\Modules\Medication\Domain\MedicationStatus;
use App\Modules\Medication\Infrastructure\Persistence\Medication;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LogicException;

/**
 * Build tenant-consistent synthetic medication fixtures.
 *
 * @extends Factory<Medication>
 */
final class MedicationFactory extends Factory
{
    protected $model = Medication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'organization_id' => static function (array $attributes): int {
                $patientId = $attributes['patient_id'] ?? null;
                if (! is_int($patientId)) {
                    throw new LogicException('MedicationFactory requires a persisted patient identifier.');
                }

                $patient = Patient::query()->find($patientId);
                if (! $patient instanceof Patient) {
                    throw new LogicException('MedicationFactory could not resolve its patient.');
                }

                return $patient->organization_id;
            },
            'public_id' => Str::uuid7()->toString(),
            'source' => MedicationSource::ProfessionalRecord,
            'name' => 'Medicamento sintético',
            'presentation' => 'Comprimido 10 mg',
            'schedule_label' => 'Una vez al día',
            'status' => MedicationStatus::Active,
            'recorded_by_identity_public_id' => Str::uuid7()->toString(),
        ];
    }

    /**
     * Mark the medication as a patient-provided declaration.
     */
    public function declaredByPatient(): self
    {
        return $this->state(fn (): array => [
            'source' => MedicationSource::PatientDeclaration,
            'recorded_by_identity_public_id' => null,
        ]);
    }
}
