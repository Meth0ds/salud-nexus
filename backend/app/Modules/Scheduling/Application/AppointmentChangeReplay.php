<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Domain\AppointmentChangeTransition;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentChange;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Reconstruct the committed representation for an idempotent appointment-change replay.
 */
final readonly class AppointmentChangeReplay
{
    /**
     * Rebuild the representation produced by the original committed command.
     *
     * The current appointment row may contain a later version, so replay data
     * is sourced from the append-only change record and applied in memory only.
     */
    public function appointment(
        Patient $patient,
        string $appointmentPublicId,
        string $changePublicId,
        AppointmentChangeTransition $expectedTransition,
    ): Appointment {
        $appointment = Appointment::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', $appointmentPublicId)
            ->with(['center', 'appointmentType.healthService'])
            ->firstOrFail();
        $change = AppointmentChange::query()
            ->where('organization_id', $patient->organization_id)
            ->where('appointment_id', $appointment->id)
            ->where('public_id', $changePublicId)
            ->where('transition', $expectedTransition->value)
            ->with(['fromSlot', 'toSlot'])
            ->first();

        if (! $change instanceof AppointmentChange) {
            // A completed idempotency record must always resolve to its immutable transition.
            throw new ConflictHttpException;
        }

        $slot = $expectedTransition === AppointmentChangeTransition::Rescheduled
            ? $change->toSlot
            : $change->fromSlot;

        if (! $slot instanceof AppointmentSlot) {
            throw new ConflictHttpException;
        }

        // Mutate only this in-memory model; the current database row may already be at a later version.
        $appointment->forceFill([
            'status' => $change->to_status,
            'slot_id' => $slot->id,
            'version' => $change->to_version,
            'location_label' => $slot->location_label,
            'professional_display_name' => $slot->professional_display_name,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
        ]);
        $appointment->setRelation('slot', $slot);

        return $appointment;
    }
}
