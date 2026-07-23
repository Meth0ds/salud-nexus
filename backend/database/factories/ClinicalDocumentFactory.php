<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Documents\Domain\DocumentCategory;
use App\Modules\Documents\Domain\DocumentStatus;
use App\Modules\Documents\Infrastructure\Persistence\ClinicalDocument;
use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LogicException;

/**
 * Build tenant-consistent synthetic clinical-document fixtures.
 *
 * @extends Factory<ClinicalDocument>
 */
final class ClinicalDocumentFactory extends Factory
{
    protected $model = ClinicalDocument::class;

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
                return self::patient($attributes)->organization_id;
            },
            'center_id' => static function (array $attributes): int {
                $patient = self::patient($attributes);
                if (is_int($patient->home_center_id)) {
                    return $patient->home_center_id;
                }

                return Center::factory()->create(['organization_id' => $patient->organization_id])->id;
            },
            'public_id' => Str::uuid7()->toString(),
            'title' => 'Documento clínico sintético',
            'category' => DocumentCategory::CareSummary,
            'status' => DocumentStatus::Draft,
            'retention_until' => null,
        ];
    }

    /**
     * Resolve the persisted patient used by dependent tenant attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function patient(array $attributes): Patient
    {
        $patientId = $attributes['patient_id'] ?? null;
        if (! is_int($patientId)) {
            throw new LogicException('ClinicalDocumentFactory requires a persisted patient identifier.');
        }

        $patient = Patient::query()->find($patientId);
        if (! $patient instanceof Patient) {
            throw new LogicException('ClinicalDocumentFactory could not resolve its patient.');
        }

        return $patient;
    }
}
