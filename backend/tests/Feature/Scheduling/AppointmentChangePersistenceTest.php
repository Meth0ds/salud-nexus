<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Scheduling\Domain\AppointmentCancellationReason;
use App\Modules\Scheduling\Domain\AppointmentChangeTransition;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentChange;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlotAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/**
 * Verify typed persistence and append-only history for appointment changes.
 */
final class AppointmentChangePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_factory_appointments_own_exactly_one_active_slot(): void
    {
        $appointment = Appointment::factory()->create();

        $allocation = $appointment->activeSlotAllocation;
        $slot = $appointment->slot;

        self::assertInstanceOf(AppointmentSlotAllocation::class, $allocation);
        self::assertInstanceOf(AppointmentSlot::class, $slot);
        self::assertTrue($allocation->appointment->is($appointment));
        self::assertTrue($allocation->slot->is($slot));
        self::assertTrue($slot->activeAllocation?->is($allocation));
        self::assertSame(1, $appointment->refresh()->version);
    }

    public function test_appointment_changes_are_cast_to_domain_values(): void
    {
        $change = $this->persistCancellationChange();

        self::assertSame(AppointmentChangeTransition::Cancelled, $change->transition);
        self::assertSame(AppointmentStatus::Scheduled, $change->from_status);
        self::assertSame(AppointmentStatus::Cancelled, $change->to_status);
        self::assertSame(AppointmentCancellationReason::PlansChanged, $change->reason_code);
        self::assertSame(1, $change->from_version);
        self::assertSame(2, $change->to_version);
        self::assertSame($change->appointment_id, $change->appointment->id);
        self::assertSame($change->identity_account_id, $change->actor->id);
    }

    public function test_appointment_changes_cannot_be_updated(): void
    {
        $change = $this->persistCancellationChange();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Appointment changes are append-only and cannot be updated.');

        $change->update(['reason_code' => AppointmentCancellationReason::Other]);
    }

    public function test_appointment_changes_cannot_be_deleted(): void
    {
        $change = $this->persistCancellationChange();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Appointment changes are append-only and cannot be deleted.');

        $change->delete();
    }

    private function persistCancellationChange(): AppointmentChange
    {
        $appointment = Appointment::factory()->create();
        $actor = IdentityAccount::factory()->create();

        return AppointmentChange::query()->create([
            'organization_id' => $appointment->organization_id,
            'appointment_id' => $appointment->id,
            'identity_account_id' => $actor->id,
            'public_id' => Str::uuid7()->toString(),
            'transition' => AppointmentChangeTransition::Cancelled,
            'from_status' => AppointmentStatus::Scheduled,
            'to_status' => AppointmentStatus::Cancelled,
            'from_slot_id' => $appointment->slot_id,
            'to_slot_id' => null,
            'reason_code' => AppointmentCancellationReason::PlansChanged,
            'from_version' => 1,
            'to_version' => 2,
            'request_public_id' => Str::uuid7()->toString(),
            'occurred_at' => now(),
        ]);
    }
}
