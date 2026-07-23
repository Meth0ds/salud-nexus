<?php

declare(strict_types=1);

namespace Tests\Feature\Patient;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Patients\Infrastructure\Persistence\PatientPortalLink;
use App\Modules\Scheduling\Domain\AppointmentRescheduled;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use App\Modules\Scheduling\Infrastructure\Persistence\HealthService;
use App\Shared\Domain\Identity\PublicId;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * Verify that rescheduling preserves the original reservation until commit.
 */
final class PatientAppointmentRescheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_reschedule_to_a_compatible_open_slot_atomically(): void
    {
        Event::fake([AppointmentRescheduled::class]);
        $context = $this->portalContext();
        $target = $this->targetSlot($context);

        $this->actingAs($context['account'], 'web')
            ->withHeaders($this->mutationHeaders('reschedule-appointment-0001'))
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $target->public_id])
            ->assertOk()
            ->assertHeader('ETag', '"v2"')
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.id', $context['appointment']->public_id)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.starts_at', $target->starts_at->utc()->format(DATE_ATOM));

        $appointment = $context['appointment']->fresh();
        self::assertInstanceOf(Appointment::class, $appointment);
        self::assertSame($target->id, $appointment->slot_id);
        self::assertSame(2, $appointment->version);
        $this->assertDatabaseHas('appointment_slot_allocations', [
            'appointment_id' => $appointment->id,
            'slot_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('appointment_slot_allocations', [
            'slot_id' => $context['slot']->id,
        ]);
        $this->assertDatabaseHas('appointment_changes', [
            'appointment_id' => $appointment->id,
            'transition' => 'rescheduled',
            'from_status' => 'scheduled',
            'to_status' => 'scheduled',
            'from_slot_id' => $context['slot']->id,
            'to_slot_id' => $target->id,
            'reason_code' => null,
            'from_version' => 1,
            'to_version' => 2,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'organization_public_id' => $context['organization']->public_id,
            'action' => 'patient.appointment.rescheduled',
            'target_public_id' => $appointment->public_id,
            'result' => 'succeeded',
        ]);
        Event::assertDispatched(
            AppointmentRescheduled::class,
            static fn (AppointmentRescheduled $event): bool => $event->appointmentId
                === $appointment->public_id,
        );

        $this->getJson('/api/v1/patient/booking-options')
            ->assertOk()
            ->assertJsonFragment(['id' => $context['slot']->public_id])
            ->assertJsonMissing(['id' => $target->public_id]);
    }

    public function test_identical_reschedule_retry_replays_without_moving_twice(): void
    {
        $context = $this->portalContext();
        $target = $this->targetSlot($context);
        $headers = $this->mutationHeaders('reschedule-appointment-0002');
        $payload = ['slot_id' => $target->public_id];

        $this->actingAs($context['account'], 'web')
            ->withHeaders($headers)
            ->postJson($this->rescheduleUrl($context), $payload)
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson($this->rescheduleUrl($context), $payload)
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertHeader('ETag', '"v2"');

        self::assertSame(1, $context['appointment']->changes()->count());
        self::assertSame($target->id, $context['appointment']->fresh()?->slot_id);
    }

    public function test_old_idempotency_key_replays_its_original_version_after_a_later_change(): void
    {
        $context = $this->portalContext();
        $firstTarget = $this->targetSlot($context);
        $secondTarget = $this->targetSlot($context, 2);
        $firstHeaders = $this->mutationHeaders('reschedule-appointment-historical-0001');
        $firstPayload = ['slot_id' => $firstTarget->public_id];

        $this->actingAs($context['account'], 'web')
            ->withHeaders($firstHeaders)
            ->postJson($this->rescheduleUrl($context), $firstPayload)
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeaders([
            'Idempotency-Key' => 'reschedule-appointment-historical-0002',
            'If-Match' => '"v2"',
        ])->postJson($this->rescheduleUrl($context), ['slot_id' => $secondTarget->public_id])
            ->assertOk()
            ->assertJsonPath('data.version', 3);

        $this->withHeaders($firstHeaders)
            ->postJson($this->rescheduleUrl($context), $firstPayload)
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertHeader('ETag', '"v2"')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.starts_at', $firstTarget->starts_at->utc()->format(DATE_ATOM));

        $current = $context['appointment']->fresh();
        self::assertInstanceOf(Appointment::class, $current);
        self::assertSame(3, $current->version);
        self::assertSame($secondTarget->id, $current->slot_id);
        self::assertSame(2, $current->changes()->count());
    }

    public function test_lost_race_for_target_slot_preserves_the_original_reservation(): void
    {
        $context = $this->portalContext();
        $target = $this->targetSlot($context);
        $otherPatient = Patient::factory()->create([
            'organization_id' => $context['organization']->id,
            'home_center_id' => $context['center']->id,
        ]);
        Appointment::factory()->create([
            'organization_id' => $context['organization']->id,
            'patient_id' => $otherPatient->id,
            'center_id' => $context['center']->id,
            'appointment_type_id' => $context['appointmentType']->id,
            'slot_id' => $target->id,
            'starts_at' => $target->starts_at,
            'ends_at' => $target->ends_at,
            'center_timezone' => $context['center']->timezone,
        ]);

        $this->actingAs($context['account'], 'web')
            ->withHeaders($this->mutationHeaders('reschedule-appointment-0003'))
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $target->public_id])
            ->assertConflict();

        $this->assertOriginalReservation($context);
    }

    public function test_foreign_or_incompatible_target_slot_is_not_disclosed(): void
    {
        $context = $this->portalContext();
        $foreign = $this->portalContext();
        $otherType = AppointmentType::factory()->create([
            'organization_id' => $context['organization']->id,
        ]);
        $incompatible = AppointmentSlot::factory()->create([
            'organization_id' => $context['organization']->id,
            'center_id' => $context['center']->id,
            'appointment_type_id' => $otherType->id,
            'starts_at' => $context['slot']->starts_at->addDays(2),
            'ends_at' => $context['slot']->ends_at->addDays(2),
        ]);

        $this->actingAs($context['account'], 'web')
            ->withHeaders($this->mutationHeaders('reschedule-appointment-0004'))
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $foreign['slot']->public_id])
            ->assertNotFound();

        $this->withHeaders($this->mutationHeaders('reschedule-appointment-0005'))
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $incompatible->public_id])
            ->assertNotFound();

        $this->assertOriginalReservation($context);
    }

    public function test_stale_version_preserves_the_original_reservation(): void
    {
        $context = $this->portalContext();
        $target = $this->targetSlot($context);

        $this->actingAs($context['account'], 'web')
            ->withHeaders([
                'Idempotency-Key' => 'reschedule-appointment-0006',
                'If-Match' => '"v7"',
            ])
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $target->public_id])
            ->assertConflict();

        $this->assertOriginalReservation($context);
    }

    public function test_failure_after_moving_the_allocation_rolls_back_every_change(): void
    {
        $context = $this->portalContext();
        $target = $this->targetSlot($context);
        $auditWriter = $this->app->make(AuditWriter::class);
        $this->app->instance(AuditWriter::class, $auditWriter);
        $this->app->bind(PublicIdGenerator::class, static fn (): PublicIdGenerator => new class implements PublicIdGenerator
        {
            public function generate(): PublicId
            {
                throw new RuntimeException('Synthetic identifier failure after allocation movement.');
            }
        });

        $this->actingAs($context['account'], 'web')
            ->withHeaders($this->mutationHeaders('reschedule-appointment-0007'))
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $target->public_id])
            ->assertInternalServerError();

        $this->assertOriginalReservation($context);
        $this->assertDatabaseMissing('appointment_slot_allocations', ['slot_id' => $target->id]);
    }

    public function test_reschedule_rejects_invalid_headers_unknown_fields_and_invalid_slots(): void
    {
        $context = $this->portalContext();
        $target = $this->targetSlot($context);

        $this->actingAs($context['account'], 'web')
            ->withHeaders([
                'Idempotency-Key' => 'reschedule-appointment-0008',
                'If-Match' => 'W/"v1"',
            ])
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $target->public_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('if_match');

        $this->withHeaders($this->mutationHeaders('reschedule-appointment-0009'))
            ->postJson($this->rescheduleUrl($context), [
                'slot_id' => $target->public_id,
                'force' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('force');

        $this->withHeaders($this->mutationHeaders('reschedule-appointment-0010'))
            ->postJson($this->rescheduleUrl($context), ['slot_id' => 'not-a-public-id'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slot_id');

        $this->assertOriginalReservation($context);
    }

    public function test_reschedule_requires_authentication_and_csrf_protection(): void
    {
        $context = $this->portalContext();
        $target = $this->targetSlot($context);

        $this->withHeaders($this->mutationHeaders('reschedule-appointment-0011'))
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $target->public_id])
            ->assertUnauthorized();

        $this->app['env'] = 'local';
        $this->actingAs($context['account'], 'web')
            ->withSession(['_token' => 'expected-csrf-token'])
            ->withHeaders($this->mutationHeaders('reschedule-appointment-0012'))
            ->postJson($this->rescheduleUrl($context), ['slot_id' => $target->public_id])
            ->assertStatus(419);

        $this->assertOriginalReservation($context);
    }

    /**
     * Build a complete patient portal fixture for rescheduling scenarios.
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
    private function portalContext(): array
    {
        $startsAt = CarbonImmutable::now('UTC')->addDay()->startOfMinute();
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
     * Create an open slot compatible with the current appointment.
     *
     * @param  array{organization: Organization, center: Center, appointmentType: AppointmentType, slot: AppointmentSlot}  $context
     */
    private function targetSlot(array $context, int $daysAfterCurrent = 1): AppointmentSlot
    {
        return AppointmentSlot::factory()->create([
            'organization_id' => $context['organization']->id,
            'center_id' => $context['center']->id,
            'appointment_type_id' => $context['appointmentType']->id,
            'starts_at' => $context['slot']->starts_at->addDays($daysAfterCurrent),
            'ends_at' => $context['slot']->ends_at->addDays($daysAfterCurrent),
            'location_label' => 'Consulta 3',
        ]);
    }

    /**
     * Build the required idempotency and optimistic concurrency headers.
     *
     * @return array{Idempotency-Key: string, If-Match: string}
     */
    private function mutationHeaders(string $idempotencyKey): array
    {
        return ['Idempotency-Key' => $idempotencyKey, 'If-Match' => '"v1"'];
    }

    /**
     * Build the canonical patient appointment rescheduling URL.
     *
     * @param  array{appointment: Appointment}  $context
     */
    private function rescheduleUrl(array $context): string
    {
        return '/api/v1/patient/appointments/'
            .$context['appointment']->public_id
            .'/reschedules';
    }

    /**
     * Assert that a rejected reschedule preserved the original reservation.
     *
     * @param  array{appointment: Appointment, slot: AppointmentSlot}  $context
     */
    private function assertOriginalReservation(array $context): void
    {
        $appointment = $context['appointment']->fresh();

        self::assertInstanceOf(Appointment::class, $appointment);
        self::assertSame($context['slot']->id, $appointment->slot_id);
        self::assertSame(1, $appointment->version);
        self::assertSame(0, $appointment->changes()->count());
        $this->assertDatabaseHas('appointment_slot_allocations', [
            'appointment_id' => $appointment->id,
            'slot_id' => $context['slot']->id,
        ]);
    }
}
