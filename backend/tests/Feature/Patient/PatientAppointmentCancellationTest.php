<?php

declare(strict_types=1);

namespace Tests\Feature\Patient;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Patients\Infrastructure\Persistence\PatientPortalLink;
use App\Modules\Scheduling\Domain\AppointmentCancelled;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use App\Modules\Scheduling\Infrastructure\Persistence\HealthService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Exercise the complete patient cancellation boundary, including recovery paths.
 */
final class PatientAppointmentCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_exposes_the_strong_version_validator(): void
    {
        $context = $this->portalContext();

        $this->actingAs($context['account'], 'web')
            ->getJson($this->appointmentUrl($context))
            ->assertOk()
            ->assertHeader('ETag', '"v1"')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.change_allowed', true);

        self::assertIsString($this->getJson($this->appointmentUrl($context))->json('data.change_deadline'));
    }

    public function test_patient_can_cancel_an_owned_future_appointment_atomically(): void
    {
        Event::fake([AppointmentCancelled::class]);
        $context = $this->portalContext();

        $response = $this->actingAs($context['account'], 'web')
            ->withHeaders($this->mutationHeaders('cancel-appointment-0001'))
            ->postJson($this->cancellationUrl($context), [
                'reason_code' => 'plans_changed',
            ])
            ->assertOk()
            ->assertHeader('ETag', '"v2"')
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.id', $context['appointment']->public_id)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.change_allowed', false);

        self::assertIsString($response->json('meta.request_id'));
        $this->assertDatabaseMissing('appointment_slot_allocations', [
            'appointment_id' => $context['appointment']->id,
        ]);
        $this->assertDatabaseHas('appointment_changes', [
            'organization_id' => $context['organization']->id,
            'appointment_id' => $context['appointment']->id,
            'identity_account_id' => $context['account']->id,
            'transition' => 'cancelled',
            'from_status' => 'scheduled',
            'to_status' => 'cancelled',
            'from_slot_id' => $context['slot']->id,
            'to_slot_id' => null,
            'reason_code' => 'plans_changed',
            'from_version' => 1,
            'to_version' => 2,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'organization_public_id' => $context['organization']->public_id,
            'action' => 'patient.appointment.cancelled',
            'target_public_id' => $context['appointment']->public_id,
            'result' => 'succeeded',
        ]);
        Event::assertDispatched(
            AppointmentCancelled::class,
            static fn (AppointmentCancelled $event): bool => $event->appointmentId
                === $context['appointment']->public_id,
        );

        // Releasing the allocation makes the same center slot bookable again.
        $this->getJson('/api/v1/patient/booking-options')
            ->assertOk()
            ->assertJsonPath(
                'data.appointment_types.0.slots.0.id',
                $context['slot']->public_id,
            );
    }

    public function test_identical_cancellation_retry_replays_without_a_second_transition(): void
    {
        $context = $this->portalContext();
        $headers = $this->mutationHeaders('cancel-appointment-0002');
        $payload = ['reason_code' => 'transport_issue'];

        $this->actingAs($context['account'], 'web')
            ->withHeaders($headers)
            ->postJson($this->cancellationUrl($context), $payload)
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');

        $this->withHeaders($headers)
            ->postJson($this->cancellationUrl($context), $payload)
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertHeader('ETag', '"v2"');

        self::assertSame(1, $context['appointment']->changes()->count());
        self::assertSame(2, $context['appointment']->fresh()?->version);
    }

    public function test_reusing_an_idempotency_key_with_different_intent_is_rejected(): void
    {
        $context = $this->portalContext();
        $headers = $this->mutationHeaders('cancel-appointment-0003');

        $this->actingAs($context['account'], 'web')
            ->withHeaders($headers)
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'plans_changed'])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'other'])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json');

        self::assertSame(1, $context['appointment']->changes()->count());
    }

    public function test_stale_version_does_not_release_or_modify_the_appointment(): void
    {
        $context = $this->portalContext();

        $this->actingAs($context['account'], 'web')
            ->withHeaders([
                'Idempotency-Key' => 'cancel-appointment-0004',
                'If-Match' => '"v9"',
            ])
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'other'])
            ->assertConflict();

        $this->assertUnchangedScheduledAppointment($context['appointment']);
    }

    public function test_change_cutoff_is_enforced_by_the_backend(): void
    {
        $context = $this->portalContext(startsAt: CarbonImmutable::now('UTC')->addMinutes(119));

        $this->actingAs($context['account'], 'web')
            ->withHeaders($this->mutationHeaders('cancel-appointment-0005'))
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'feeling_better'])
            ->assertConflict();

        $this->assertUnchangedScheduledAppointment($context['appointment']);
    }

    public function test_non_scheduled_appointment_cannot_be_cancelled_again(): void
    {
        $context = $this->portalContext(status: AppointmentStatus::Cancelled);

        $this->actingAs($context['account'], 'web')
            ->withHeaders($this->mutationHeaders('cancel-appointment-0006'))
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'other'])
            ->assertConflict();

        self::assertSame(0, $context['appointment']->changes()->count());
    }

    public function test_foreign_appointment_is_not_disclosed_or_modified(): void
    {
        $mine = $this->portalContext();
        $other = $this->portalContext();

        $this->actingAs($mine['account'], 'web')
            ->withHeaders($this->mutationHeaders('cancel-appointment-0007'))
            ->postJson($this->cancellationUrl($other), ['reason_code' => 'other'])
            ->assertNotFound();

        $this->assertDatabaseHas('appointment_slot_allocations', [
            'appointment_id' => $other['appointment']->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'organization_public_id' => $mine['organization']->public_id,
            'action' => 'patient.appointment.cancellation_denied',
            'target_public_id' => $other['appointment']->public_id,
            'result' => 'denied',
        ]);
    }

    public function test_cancellation_rejects_weak_validators_unknown_fields_and_invalid_reasons(): void
    {
        $context = $this->portalContext();

        $this->actingAs($context['account'], 'web')
            ->withHeaders([
                'Idempotency-Key' => 'cancel-appointment-0008',
                'If-Match' => 'W/"v1"',
            ])
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('if_match');

        $this->withHeaders($this->mutationHeaders('cancel-appointment-0009'))
            ->postJson($this->cancellationUrl($context), [
                'reason_code' => 'other',
                'notes' => 'Free text must never enter this boundary.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notes');

        $this->withHeaders($this->mutationHeaders('cancel-appointment-0010'))
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'clinical_detail'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason_code');

        $this->assertUnchangedScheduledAppointment($context['appointment']);
    }

    public function test_cancellation_requires_authentication_and_csrf_protection(): void
    {
        $context = $this->portalContext();

        $this->withHeaders($this->mutationHeaders('cancel-appointment-0011'))
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'other'])
            ->assertUnauthorized();

        $this->app['env'] = 'local';
        $this->actingAs($context['account'], 'web')
            ->withSession(['_token' => 'expected-csrf-token'])
            ->withHeaders($this->mutationHeaders('cancel-appointment-0012'))
            ->postJson($this->cancellationUrl($context), ['reason_code' => 'other'])
            ->assertStatus(419);

        $this->assertUnchangedScheduledAppointment($context['appointment']);
    }

    /**
     * Build a complete patient portal fixture for cancellation scenarios.
     *
     * @return array{
     *     account: IdentityAccount,
     *     organization: Organization,
     *     center: Center,
     *     patient: Patient,
     *     appointmentType: AppointmentType,
     *     slot: AppointmentSlot,
     *     appointment: Appointment
     * }
     */
    private function portalContext(
        ?CarbonImmutable $startsAt = null,
        AppointmentStatus $status = AppointmentStatus::Scheduled,
    ): array {
        $startsAt ??= CarbonImmutable::now('UTC')->addDay()->startOfMinute();
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
        $service = HealthService::factory()->create(['organization_id' => $organization->id]);
        $appointmentType = AppointmentType::factory()->create([
            'organization_id' => $organization->id,
            'health_service_id' => $service->id,
        ]);
        $slot = AppointmentSlot::factory()->create([
            'organization_id' => $organization->id,
            'center_id' => $center->id,
            'appointment_type_id' => $appointmentType->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
        ]);
        $appointment = Appointment::factory()->create([
            'organization_id' => $organization->id,
            'patient_id' => $patient->id,
            'center_id' => $center->id,
            'appointment_type_id' => $appointmentType->id,
            'slot_id' => $slot->id,
            'status' => $status,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'center_timezone' => $center->timezone,
        ]);

        return compact(
            'account',
            'organization',
            'center',
            'patient',
            'appointmentType',
            'slot',
            'appointment',
        );
    }

    /**
     * Build the required idempotency and optimistic concurrency headers.
     *
     * @return array{Idempotency-Key: string, If-Match: string}
     */
    private function mutationHeaders(string $idempotencyKey): array
    {
        return [
            'Idempotency-Key' => $idempotencyKey,
            'If-Match' => '"v1"',
        ];
    }

    /**
     * Build the canonical patient appointment resource URL.
     *
     * @param  array{appointment: Appointment}  $context
     */
    private function appointmentUrl(array $context): string
    {
        return '/api/v1/patient/appointments/'.$context['appointment']->public_id;
    }

    /**
     * Build the canonical patient appointment cancellation URL.
     *
     * @param  array{appointment: Appointment}  $context
     */
    private function cancellationUrl(array $context): string
    {
        return $this->appointmentUrl($context).'/cancellations';
    }

    /**
     * Assert that a rejected cancellation preserved the scheduled appointment.
     */
    private function assertUnchangedScheduledAppointment(Appointment $appointment): void
    {
        $appointment->refresh();

        self::assertSame(AppointmentStatus::Scheduled, $appointment->status);
        self::assertSame(1, $appointment->version);
        self::assertSame(0, $appointment->changes()->count());
        $this->assertDatabaseHas('appointment_slot_allocations', [
            'appointment_id' => $appointment->id,
            'slot_id' => $appointment->slot_id,
        ]);
    }
}
