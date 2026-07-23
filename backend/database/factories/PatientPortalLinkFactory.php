<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Patients\Infrastructure\Persistence\PatientPortalLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Build tenant-consistent patient-to-identity portal links.
 *
 * @extends Factory<PatientPortalLink>
 */
final class PatientPortalLinkFactory extends Factory
{
    protected $model = PatientPortalLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'patient_id' => static fn (array $attributes): int => Patient::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
            'identity_account_id' => IdentityAccountFactory::new(),
        ];
    }
}
