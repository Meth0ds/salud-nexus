<?php

declare(strict_types=1);

namespace Tests\Feature\Medication;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Medication\Domain\MedicationDeclared;
use App\Modules\Medication\Domain\MedicationRenewalRequested;
use App\Modules\Medication\Domain\MedicationSource;
use App\Modules\Medication\Infrastructure\Persistence\Medication;
use App\Modules\Medication\Infrastructure\Persistence\MedicationRenewalRequest;
use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Patients\Infrastructure\Persistence\PatientPortalLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class PatientMedicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_preserves_the_patient_organization_boundary(): void
    {
        $medication = Medication::factory()->create();

        self::assertTrue(
            Patient::query()
                ->whereKey($medication->patient_id)
                ->where('organization_id', $medication->organization_id)
                ->exists(),
        );
    }

    public function test_medication_endpoints_require_a_session_authenticated_identity(): void
    {
        $this->getJson('/api/v1/patient/medications')->assertUnauthorized();
        $this->postJson('/api/v1/patient/medications/declarations', [])->assertUnauthorized();
    }

    public function test_list_is_patient_scoped_minimized_and_preserves_source_separation(): void
    {
        $mine = $this->portalContext();
        $other = $this->portalContext();
        $professional = $this->medicationFor($mine, MedicationSource::ProfessionalRecord);
        $declaration = $this->medicationFor($mine, MedicationSource::PatientDeclaration);
        $foreign = $this->medicationFor($other, MedicationSource::ProfessionalRecord);

        $response = $this->actingAs($mine['account'], 'web')
            ->getJson('/api/v1/patient/medications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $professional->public_id,
                'source' => 'professional_record',
                'can_request_renewal' => true,
                'renewal_request_status' => null,
            ])
            ->assertJsonFragment([
                'id' => $declaration->public_id,
                'source' => 'patient_declaration',
                'can_request_renewal' => false,
            ])
            ->assertJsonMissing(['id' => $foreign->public_id])
            ->assertJsonMissingPath('data.0.patient_id')
            ->assertJsonMissingPath('data.0.organization_id')
            ->assertJsonMissingPath('data.0.recorded_by_identity_public_id');

        self::assertSame(['data', 'meta'], array_keys($response->json()));
    }

    public function test_foreign_medication_detail_is_a_neutral_not_found_and_is_audited(): void
    {
        $mine = $this->portalContext();
        $other = $this->portalContext();
        $foreign = $this->medicationFor($other, MedicationSource::ProfessionalRecord);

        $this->actingAs($mine['account'], 'web')
            ->getJson('/api/v1/patient/medications/'.$foreign->public_id)
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertDatabaseHas('audit_events', [
            'organization_public_id' => $mine['organization']->public_id,
            'action' => 'patient.medication.view_denied',
            'target_public_id' => $foreign->public_id,
            'result' => 'denied',
        ]);
    }

    public function test_patient_declaration_is_idempotent_and_cannot_become_a_professional_record(): void
    {
        Event::fake([MedicationDeclared::class]);
        $context = $this->portalContext();
        $payload = [
            'name' => '  Medicamento comunicado  ',
            'presentation' => 'Comprimido sintético',
            'schedule_label' => 'Una vez al día',
        ];

        $first = $this->actingAs($context['account'], 'web')
            ->withHeader('Idempotency-Key', 'medication-declare-0001')
            ->postJson('/api/v1/patient/medications/declarations', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.source', 'patient_declaration')
            ->assertJsonPath('data.name', 'Medicamento comunicado')
            ->assertJsonPath('data.can_request_renewal', false);

        $second = $this->withHeader('Idempotency-Key', 'medication-declare-0001')
            ->postJson('/api/v1/patient/medications/declarations', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame(1, Medication::query()->count());
        $this->assertDatabaseHas('medications', [
            'public_id' => $first->json('data.id'),
            'organization_id' => $context['organization']->id,
            'patient_id' => $context['patient']->id,
            'source' => 'patient_declaration',
            'recorded_by_identity_public_id' => null,
        ]);
        Event::assertDispatchedTimes(MedicationDeclared::class, 1);
    }

    public function test_reusing_a_declaration_key_with_a_different_payload_conflicts(): void
    {
        $context = $this->portalContext();

        $this->actingAs($context['account'], 'web')
            ->withHeader('Idempotency-Key', 'medication-declare-0002')
            ->postJson('/api/v1/patient/medications/declarations', [
                'name' => 'Medicamento A',
                'presentation' => null,
                'schedule_label' => 'Por la mañana',
            ])
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'medication-declare-0002')
            ->postJson('/api/v1/patient/medications/declarations', [
                'name' => 'Medicamento B',
                'presentation' => null,
                'schedule_label' => 'Por la noche',
            ])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json');

        self::assertSame(1, Medication::query()->count());
    }

    public function test_declaration_rejects_unknown_fields_and_missing_idempotency_proof(): void
    {
        $context = $this->portalContext();

        $this->actingAs($context['account'], 'web')
            ->withHeader('Idempotency-Key', 'medication-declare-0003')
            ->postJson('/api/v1/patient/medications/declarations', [
                'name' => 'Medicamento sintético',
                'schedule_label' => 'Una vez al día',
                'patient_id' => $context['patient']->public_id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.request.0', 'Unknown fields are not allowed.');

        $this->withoutHeader('Idempotency-Key')->postJson('/api/v1/patient/medications/declarations', [
            'name' => 'Medicamento sintético',
            'schedule_label' => 'Una vez al día',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.idempotency_key.0', 'The idempotency key field is required.');
    }

    public function test_renewal_request_is_only_for_an_owned_active_professional_record_and_replays(): void
    {
        Event::fake([MedicationRenewalRequested::class]);
        $context = $this->portalContext();
        $medication = $this->medicationFor($context, MedicationSource::ProfessionalRecord);

        $first = $this->actingAs($context['account'], 'web')
            ->withHeader('Idempotency-Key', 'medication-renewal-0001')
            ->postJson('/api/v1/patient/medications/'.$medication->public_id.'/renewal-requests', [])
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.medication_id', $medication->public_id)
            ->assertJsonPath('data.status', 'submitted');

        $second = $this->withHeader('Idempotency-Key', 'medication-renewal-0001')
            ->postJson('/api/v1/patient/medications/'.$medication->public_id.'/renewal-requests', [])
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame(1, MedicationRenewalRequest::query()->count());
        Event::assertDispatchedTimes(MedicationRenewalRequested::class, 1);

        $this->getJson('/api/v1/patient/medications')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $medication->public_id,
                'can_request_renewal' => false,
                'renewal_request_status' => 'submitted',
            ]);
    }

    public function test_a_second_pending_renewal_and_patient_declarations_are_rejected(): void
    {
        $context = $this->portalContext();
        $professional = $this->medicationFor($context, MedicationSource::ProfessionalRecord);
        $declaration = $this->medicationFor($context, MedicationSource::PatientDeclaration);

        $this->actingAs($context['account'], 'web')
            ->withHeader('Idempotency-Key', 'medication-renewal-0002')
            ->postJson('/api/v1/patient/medications/'.$professional->public_id.'/renewal-requests', [])
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'medication-renewal-0003')
            ->postJson('/api/v1/patient/medications/'.$professional->public_id.'/renewal-requests', [])
            ->assertConflict();

        $this->withHeader('Idempotency-Key', 'medication-renewal-0004')
            ->postJson('/api/v1/patient/medications/'.$declaration->public_id.'/renewal-requests', [])
            ->assertNotFound();
    }

    public function test_medication_mutations_require_csrf_outside_testing_environment(): void
    {
        $context = $this->portalContext();
        $this->app['env'] = 'local';

        $this->actingAs($context['account'], 'web')
            ->withSession(['_token' => 'expected-csrf-token'])
            ->withHeader('Idempotency-Key', 'medication-declare-0005')
            ->postJson('/api/v1/patient/medications/declarations', [
                'name' => 'Medicamento sintético',
                'schedule_label' => 'Una vez al día',
            ])
            ->assertStatus(419)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    /**
     * Build an authenticated patient portal fixture for medication scenarios.
     *
     * @return array{account: IdentityAccount, organization: Organization, center: Center, patient: Patient}
     */
    private function portalContext(): array
    {
        $account = IdentityAccount::factory()->create();
        $organization = Organization::factory()->create();
        $center = Center::factory()->create(['organization_id' => $organization->id]);
        $patient = Patient::factory()->create([
            'organization_id' => $organization->id,
            'home_center_id' => $center->id,
        ]);
        PatientPortalLink::factory()->create([
            'organization_id' => $organization->id,
            'patient_id' => $patient->id,
            'identity_account_id' => $account->id,
        ]);

        return compact('account', 'organization', 'center', 'patient');
    }

    /**
     * Create a medication with the requested provenance for the patient.
     *
     * @param  array{account: IdentityAccount, organization: Organization, center: Center, patient: Patient}  $context
     */
    private function medicationFor(array $context, MedicationSource $source): Medication
    {
        return Medication::factory()->create([
            'organization_id' => $context['organization']->id,
            'patient_id' => $context['patient']->id,
            'source' => $source,
            'recorded_by_identity_public_id' => $source === MedicationSource::ProfessionalRecord
                ? $context['account']->public_id
                : null,
        ]);
    }
}
