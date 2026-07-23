<?php

declare(strict_types=1);

namespace Tests\Feature\Patient;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Patients\Infrastructure\Persistence\PatientPortalLink;
use App\Modules\Scheduling\Domain\AppointmentBooked;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use App\Modules\Scheduling\Infrastructure\Persistence\HealthService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class PatientPortalAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_allows_only_one_center_per_organization(): void
    {
        $organization = Organization::factory()->create();
        Center::factory()->create(['organization_id' => $organization->id]);

        $this->expectException(QueryException::class);
        Center::factory()->create(['organization_id' => $organization->id]);
    }

    public function test_patient_endpoints_require_a_session_authenticated_identity(): void
    {
        $this->getJson('/api/v1/patient/profile')->assertUnauthorized();
        $this->getJson('/api/v1/patient/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/patient/appointments')->assertUnauthorized();
        $this->getJson('/api/v1/patient/booking-options')->assertUnauthorized();
        $this->postJson('/api/v1/patient/appointments', [])->assertUnauthorized();
    }

    public function test_profile_and_dashboard_are_minimized_and_resolved_from_the_portal_link(): void
    {
        $context = $this->portalContext(
            organizationName: 'Organizacion Norte',
            centerName: 'Centro Sol',
            timezone: 'Europe/Madrid',
        );
        $appointment = Appointment::factory()->create([
            'organization_id' => $context['organization']->id,
            'patient_id' => $context['patient']->id,
            'center_id' => $context['center']->id,
            'appointment_type_id' => $context['appointmentType']->id,
            'slot_id' => $context['slot']->id,
            'starts_at' => $context['slot']->starts_at,
            'ends_at' => $context['slot']->ends_at,
            'center_timezone' => 'Europe/Madrid',
        ]);
        AppointmentSlot::factory()->create([
            'organization_id' => $context['organization']->id,
            'center_id' => $context['center']->id,
            'appointment_type_id' => $context['appointmentType']->id,
            'starts_at' => CarbonImmutable::parse('2026-08-04 08:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-04 08:30:00 UTC'),
        ]);

        $profile = $this->actingAs($context['account'], 'web')
            ->getJson('/api/v1/patient/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $context['patient']->public_id)
            ->assertJsonPath('data.organization.id', $context['organization']->public_id)
            ->assertJsonPath('data.home_center.id', $context['center']->public_id)
            ->assertJsonPath('data.home_center.timezone', 'Europe/Madrid')
            ->assertJsonMissingPath('data.identity_account_id')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.organization.internal_id');

        self::assertSame(
            ['id', 'record_number', 'display_name', 'date_of_birth', 'organization', 'home_center'],
            array_keys($profile->json('data')),
        );

        $dashboard = $this->getJson('/api/v1/patient/dashboard')
            ->assertOk()
            ->assertJsonPath('data.upcoming_appointments_count', 1)
            ->assertJsonPath('data.next_appointment.id', $appointment->public_id)
            ->assertJsonPath('data.available_appointment_types_count', 1);

        self::assertSame(
            ['upcoming_appointments_count', 'next_appointment', 'available_appointment_types_count'],
            array_keys($dashboard->json('data')),
        );
    }

    public function test_an_authenticated_identity_without_an_active_portal_link_is_forbidden(): void
    {
        $account = IdentityAccount::factory()->create();

        $this->actingAs($account, 'web')
            ->getJson('/api/v1/patient/profile')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_appointment_list_and_show_are_scoped_to_the_linked_patient_and_organization(): void
    {
        $mine = $this->portalContext();
        $other = $this->portalContext();
        $myAppointment = $this->appointmentFor($mine);
        $otherAppointment = $this->appointmentFor($other);

        $response = $this->actingAs($mine['account'], 'web')
            ->getJson('/api/v1/patient/appointments?scope=all&per_page=25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myAppointment->public_id)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1);

        self::assertSame(
            ['data', 'meta'],
            array_keys($response->json()),
        );

        $this->getJson('/api/v1/patient/appointments/'.$otherAppointment->public_id)
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
        $this->assertDatabaseHas('audit_events', [
            'organization_public_id' => $mine['organization']->public_id,
            'action' => 'patient.appointment.view_denied',
            'target_public_id' => $otherAppointment->public_id,
            'result' => 'denied',
        ]);

        $this->getJson('/api/v1/patient/appointments/'.$myAppointment->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $myAppointment->public_id);
    }

    public function test_booking_options_only_expose_future_open_unreserved_slots_in_the_tenant(): void
    {
        $mine = $this->portalContext();
        $other = $this->portalContext();

        // A historical appointment must not keep an otherwise open slot occupied.
        Appointment::factory()->create([
            'organization_id' => $mine['organization']->id,
            'patient_id' => $mine['patient']->id,
            'center_id' => $mine['center']->id,
            'appointment_type_id' => $mine['appointmentType']->id,
            'slot_id' => $mine['slot']->id,
            'status' => AppointmentStatus::Cancelled,
            'starts_at' => $mine['slot']->starts_at,
            'ends_at' => $mine['slot']->ends_at,
            'center_timezone' => $mine['center']->timezone,
        ]);
        $reservedSlot = AppointmentSlot::factory()->create([
            'organization_id' => $mine['organization']->id,
            'center_id' => $mine['center']->id,
            'appointment_type_id' => $mine['appointmentType']->id,
            'starts_at' => CarbonImmutable::parse('2026-08-02 08:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-02 08:30:00 UTC'),
        ]);
        Appointment::factory()->create([
            'organization_id' => $mine['organization']->id,
            'patient_id' => $mine['patient']->id,
            'center_id' => $mine['center']->id,
            'appointment_type_id' => $mine['appointmentType']->id,
            'slot_id' => $reservedSlot->id,
            'starts_at' => $reservedSlot->starts_at,
            'ends_at' => $reservedSlot->ends_at,
            'center_timezone' => $mine['center']->timezone,
        ]);
        AppointmentSlot::factory()->blocked()->create([
            'organization_id' => $mine['organization']->id,
            'center_id' => $mine['center']->id,
            'appointment_type_id' => $mine['appointmentType']->id,
        ]);

        $response = $this->actingAs($mine['account'], 'web')
            ->getJson('/api/v1/patient/booking-options')
            ->assertOk()
            ->assertJsonCount(1, 'data.appointment_types')
            ->assertJsonPath('data.appointment_types.0.id', $mine['appointmentType']->public_id)
            ->assertJsonPath('data.appointment_types.0.attendance_mode', 'in_person')
            ->assertJsonCount(1, 'data.appointment_types.0.slots')
            ->assertJsonPath('data.appointment_types.0.slots.0.id', $mine['slot']->public_id)
            ->assertJsonPath('data.appointment_types.0.slots.0.local_starts_at', '2026-08-01T10:00:00+02:00')
            ->assertJsonMissing(['id' => $other['slot']->public_id])
            ->assertJsonMissing(['id' => $reservedSlot->public_id]);

        self::assertIsString($response->json('meta.generated_at'));
        self::assertIsString($response->json('meta.request_id'));
    }

    public function test_booking_creates_an_owned_appointment_with_utc_and_center_local_times(): void
    {
        Event::fake([AppointmentBooked::class]);
        $context = $this->portalContext();

        $response = $this->actingAs($context['account'], 'web')
            ->withHeader('Idempotency-Key', 'booking-key-00000001')
            ->postJson('/api/v1/patient/appointments', $this->bookingPayload($context))
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.attendance_mode', 'in_person')
            ->assertJsonPath('data.location_label', 'Consulta 2')
            ->assertJsonPath('data.professional_display_name', null)
            ->assertJsonPath('data.starts_at', '2026-08-01T08:00:00+00:00')
            ->assertJsonPath('data.local_starts_at', '2026-08-01T10:00:00+02:00')
            ->assertJsonMissingPath('data.patient_id')
            ->assertJsonMissingPath('data.organization_id');

        $appointmentId = $response->json('data.id');
        self::assertIsString($appointmentId);
        $this->assertDatabaseHas('appointments', [
            'public_id' => $appointmentId,
            'organization_id' => $context['organization']->id,
            'patient_id' => $context['patient']->id,
            'slot_id' => $context['slot']->id,
        ]);
        $this->assertDatabaseHas('appointment_slot_allocations', [
            'organization_id' => $context['organization']->id,
            'slot_id' => $context['slot']->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'organization_public_id' => $context['organization']->public_id,
            'action' => 'patient.appointment.booked',
            'target_public_id' => $appointmentId,
            'result' => 'succeeded',
        ]);
        Event::assertDispatched(
            AppointmentBooked::class,
            static fn (AppointmentBooked $event): bool => $event->appointmentId === $appointmentId,
        );
    }

    public function test_same_idempotency_key_and_payload_replays_the_original_booking(): void
    {
        $context = $this->portalContext();
        $payload = $this->bookingPayload($context);

        $first = $this->actingAs($context['account'], 'web')
            ->withHeader('Idempotency-Key', 'booking-key-00000002')
            ->postJson('/api/v1/patient/appointments', $payload)
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'booking-key-00000002')
            ->postJson('/api/v1/patient/appointments', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame(1, Appointment::query()->count());
    }

    public function test_same_idempotency_key_with_a_different_payload_returns_conflict(): void
    {
        $context = $this->portalContext();
        $secondSlot = AppointmentSlot::factory()->create([
            'organization_id' => $context['organization']->id,
            'center_id' => $context['center']->id,
            'appointment_type_id' => $context['appointmentType']->id,
            'starts_at' => CarbonImmutable::parse('2026-08-03 08:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-03 08:30:00 UTC'),
        ]);

        $this->actingAs($context['account'], 'web')
            ->withHeader('Idempotency-Key', 'booking-key-00000003')
            ->postJson('/api/v1/patient/appointments', $this->bookingPayload($context))
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'booking-key-00000003')
            ->postJson('/api/v1/patient/appointments', [
                'appointment_type_id' => $context['appointmentType']->public_id,
                'slot_id' => $secondSlot->public_id,
            ])
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://salud-nexus.example/problems/conflict');

        self::assertSame(1, Appointment::query()->count());
    }

    public function test_cross_tenant_booking_identifiers_are_not_disclosed_or_accepted(): void
    {
        $mine = $this->portalContext();
        $other = $this->portalContext();

        $this->actingAs($mine['account'], 'web')
            ->withHeader('Idempotency-Key', 'booking-key-00000004')
            ->postJson('/api/v1/patient/appointments', $this->bookingPayload($other))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');

        self::assertSame(0, Appointment::query()->count());
    }

    public function test_a_single_capacity_slot_cannot_be_double_booked_by_two_patients(): void
    {
        $owner = $this->portalContext();
        $otherAccount = IdentityAccount::factory()->create();
        $otherPatient = Patient::factory()->create([
            'organization_id' => $owner['organization']->id,
            'home_center_id' => $owner['center']->id,
        ]);
        PatientPortalLink::factory()->create([
            'organization_id' => $owner['organization']->id,
            'patient_id' => $otherPatient->id,
            'identity_account_id' => $otherAccount->id,
        ]);

        $this->actingAs($owner['account'], 'web')
            ->withHeader('Idempotency-Key', 'booking-key-00000005')
            ->postJson('/api/v1/patient/appointments', $this->bookingPayload($owner))
            ->assertCreated();

        $this->actingAs($otherAccount, 'web')
            ->withHeader('Idempotency-Key', 'booking-key-00000006')
            ->postJson('/api/v1/patient/appointments', $this->bookingPayload($owner))
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json');

        self::assertSame(1, Appointment::query()->count());
    }

    public function test_booking_requires_a_valid_idempotency_header(): void
    {
        $context = $this->portalContext();

        $this->actingAs($context['account'], 'web')
            ->postJson('/api/v1/patient/appointments', $this->bookingPayload($context))
            ->assertUnprocessable()
            ->assertJsonPath('errors.idempotency_key.0', 'The idempotency key field is required.');
    }

    public function test_booking_mutation_requires_csrf_when_the_application_is_not_in_testing_mode(): void
    {
        $context = $this->portalContext();
        $this->app['env'] = 'local';

        $this->actingAs($context['account'], 'web')
            ->withSession(['_token' => 'expected-csrf-token'])
            ->withHeader('Idempotency-Key', 'booking-key-00000007')
            ->postJson('/api/v1/patient/appointments', $this->bookingPayload($context))
            ->assertStatus(419)
            ->assertHeader('Content-Type', 'application/problem+json');

        self::assertSame(0, Appointment::query()->count());
    }

    /**
     * Build an authenticated patient portal fixture for appointment scenarios.
     *
     * @return array{
     *     account: IdentityAccount,
     *     organization: Organization,
     *     center: Center,
     *     patient: Patient,
     *     service: HealthService,
     *     appointmentType: AppointmentType,
     *     slot: AppointmentSlot
     * }
     */
    private function portalContext(
        string $organizationName = 'Organizacion Salud',
        string $centerName = 'Centro Aurora',
        string $timezone = 'Europe/Madrid',
    ): array {
        $account = IdentityAccount::factory()->create();
        $organization = Organization::factory()->create(['name' => $organizationName]);
        $center = Center::factory()->create([
            'organization_id' => $organization->id,
            'name' => $centerName,
            'timezone' => $timezone,
        ]);
        $patient = Patient::factory()->create([
            'organization_id' => $organization->id,
            'home_center_id' => $center->id,
        ]);
        PatientPortalLink::factory()->create([
            'organization_id' => $organization->id,
            'patient_id' => $patient->id,
            'identity_account_id' => $account->id,
        ]);
        $service = HealthService::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $appointmentType = AppointmentType::factory()->create([
            'organization_id' => $organization->id,
            'health_service_id' => $service->id,
            'duration_minutes' => 30,
            'attendance_mode' => 'in_person',
        ]);
        $slot = AppointmentSlot::factory()->create([
            'organization_id' => $organization->id,
            'center_id' => $center->id,
            'appointment_type_id' => $appointmentType->id,
            'starts_at' => CarbonImmutable::parse('2026-08-01 08:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-01 08:30:00 UTC'),
            'location_label' => 'Consulta 2',
        ]);

        return compact(
            'account',
            'organization',
            'center',
            'patient',
            'service',
            'appointmentType',
            'slot',
        );
    }

    /**
     * Create an appointment that belongs to the supplied patient context.
     *
     * @param  array{
     *     account: IdentityAccount,
     *     organization: Organization,
     *     center: Center,
     *     patient: Patient,
     *     service: HealthService,
     *     appointmentType: AppointmentType,
     *     slot: AppointmentSlot
     * }  $context
     */
    private function appointmentFor(array $context): Appointment
    {
        return Appointment::factory()->create([
            'organization_id' => $context['organization']->id,
            'patient_id' => $context['patient']->id,
            'center_id' => $context['center']->id,
            'appointment_type_id' => $context['appointmentType']->id,
            'slot_id' => $context['slot']->id,
            'starts_at' => $context['slot']->starts_at,
            'ends_at' => $context['slot']->ends_at,
            'center_timezone' => $context['center']->timezone,
        ]);
    }

    /**
     * Build the public identifier payload required to book an appointment.
     *
     * @param  array{
     *     account: IdentityAccount,
     *     organization: Organization,
     *     center: Center,
     *     patient: Patient,
     *     service: HealthService,
     *     appointmentType: AppointmentType,
     *     slot: AppointmentSlot
     * }  $context
     * @return array{appointment_type_id: string, slot_id: string}
     */
    private function bookingPayload(array $context): array
    {
        return [
            'appointment_type_id' => $context['appointmentType']->public_id,
            'slot_id' => $context['slot']->public_id,
        ];
    }
}
