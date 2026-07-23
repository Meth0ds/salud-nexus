<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Application\Ports\DomainEventPublisher;
use App\Modules\Scheduling\Domain\AppointmentChangeTransition;
use App\Modules\Scheduling\Domain\AppointmentRescheduled;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Domain\SlotStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentChange;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlotAllocation;
use App\Modules\Scheduling\Infrastructure\Persistence\IdempotencyRequest;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Move the active allocation without releasing the original reservation on failure.
 */
final readonly class ReschedulePatientAppointment
{
    private const OPERATION = 'api.v1.patient.appointments.reschedules.store';

    public function __construct(
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
        private DomainEventPublisher $events,
        private PatientAppointmentChangePolicy $policy,
        private AppointmentChangeIdempotency $idempotency,
        private AppointmentChangeReplay $replay,
    ) {}

    /**
     * Move one patient-owned appointment to a compatible, available slot.
     *
     * Both slot rows and the current allocation are locked before the move. A
     * uniqueness violation therefore rolls the complete transition back safely.
     */
    public function handle(
        IdentityAccount $account,
        Patient $patient,
        string $appointmentPublicId,
        RescheduleAppointmentData $data,
    ): AppointmentChangeResult {
        $appointmentPublicId = strtolower($appointmentPublicId);
        $requestHash = $data->requestHash($appointmentPublicId);

        try {
            $result = DB::transaction(function () use (
                $account,
                $patient,
                $appointmentPublicId,
                $data,
                $requestHash,
            ): AppointmentChangeResult {
                $existing = $this->idempotency->find(
                    $account,
                    self::OPERATION,
                    $data->idempotencyKey,
                    true,
                );

                if ($existing instanceof IdempotencyRequest) {
                    return $this->replay($existing, $patient, $appointmentPublicId, $requestHash);
                }

                $idempotency = $this->idempotency->start(
                    $account,
                    self::OPERATION,
                    $data->idempotencyKey,
                    $requestHash,
                    $this->clock->now()->modify('+24 hours'),
                );
                $appointment = $this->ownedAppointment($patient, $appointmentPublicId);

                if ($appointment->version !== $data->expectedVersion) {
                    throw new ConflictHttpException;
                }

                $this->policy->assertAllowed($appointment);

                $targetSlot = AppointmentSlot::query()
                    ->where('organization_id', $patient->organization_id)
                    ->where('center_id', $appointment->center_id)
                    ->where('appointment_type_id', $appointment->appointment_type_id)
                    ->where('public_id', strtolower($data->slotId))
                    ->where('status', SlotStatus::Open->value)
                    ->where('starts_at', '>=', $this->clock->now())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($targetSlot->id === $appointment->slot_id) {
                    throw new ConflictHttpException;
                }

                if (AppointmentSlotAllocation::query()->where('slot_id', $targetSlot->id)->exists()) {
                    throw new ConflictHttpException;
                }

                $allocation = AppointmentSlotAllocation::query()
                    ->where('organization_id', $patient->organization_id)
                    ->where('appointment_id', $appointment->id)
                    ->where('slot_id', $appointment->slot_id)
                    ->lockForUpdate()
                    ->first();

                if (! $allocation instanceof AppointmentSlotAllocation) {
                    throw new ConflictHttpException;
                }

                $fromSlot = $appointment->slot;

                if (! $fromSlot instanceof AppointmentSlot) {
                    throw new ConflictHttpException;
                }

                $fromVersion = $appointment->version;
                $occurredAt = $this->clock->now();

                // A uniqueness failure here rolls back both this move and every later snapshot update.
                $allocation->forceFill(['slot_id' => $targetSlot->id])->save();
                $appointment->forceFill([
                    'slot_id' => $targetSlot->id,
                    'version' => $fromVersion + 1,
                    'location_label' => $targetSlot->location_label,
                    'professional_display_name' => $targetSlot->professional_display_name,
                    'starts_at' => $targetSlot->starts_at,
                    'ends_at' => $targetSlot->ends_at,
                ])->save();
                $appointment->setRelation('slot', $targetSlot);
                $appointment->setRelation('activeSlotAllocation', $allocation);

                $change = AppointmentChange::query()->create([
                    'organization_id' => $patient->organization_id,
                    'appointment_id' => $appointment->id,
                    'identity_account_id' => $account->id,
                    'public_id' => $this->publicIds->generate()->toString(),
                    'transition' => AppointmentChangeTransition::Rescheduled,
                    'from_status' => AppointmentStatus::Scheduled,
                    'to_status' => AppointmentStatus::Scheduled,
                    'from_slot_id' => $fromSlot->id,
                    'to_slot_id' => $targetSlot->id,
                    'reason_code' => null,
                    'from_version' => $fromVersion,
                    'to_version' => $appointment->version,
                    'request_public_id' => $data->requestPublicId,
                    'occurred_at' => $occurredAt,
                ]);

                $this->idempotency->complete(
                    $idempotency,
                    $change->public_id,
                    $occurredAt,
                );

                return new AppointmentChangeResult(
                    $appointment,
                    false,
                    $fromSlot->public_id,
                );
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->idempotency->find(
                $account,
                self::OPERATION,
                $data->idempotencyKey,
                false,
            );

            if ($existing instanceof IdempotencyRequest) {
                return $this->replay($existing, $patient, $appointmentPublicId, $requestHash);
            }

            throw new ConflictHttpException(previous: $exception);
        }

        if (! $result->replayed) {
            if (! is_string($result->previousSlotPublicId)) {
                throw new LogicException('A committed reschedule must retain its previous public slot identifier.');
            }

            $this->events->publish(new AppointmentRescheduled(
                appointmentId: $result->appointment->public_id,
                organizationId: $patient->organization->public_id,
                actorIdentityId: $account->public_id,
                fromSlotId: $result->previousSlotPublicId,
                toSlotId: strtolower($data->slotId),
                occurredAt: $this->clock->now(),
            ));
        }

        return $result;
    }

    /**
     * Reconstruct the exact representation produced by a committed retry.
     */
    private function replay(
        IdempotencyRequest $request,
        Patient $patient,
        string $appointmentPublicId,
        string $requestHash,
    ): AppointmentChangeResult {
        $changePublicId = $this->idempotency->replayResource($request, $requestHash);

        return new AppointmentChangeResult(
            $this->replay->appointment(
                $patient,
                $appointmentPublicId,
                $changePublicId,
                AppointmentChangeTransition::Rescheduled,
            ),
            true,
        );
    }

    /**
     * Lock the appointment while enforcing both organization and patient scope.
     *
     * The ownership predicate intentionally participates in the database query
     * so an unauthorized record is never loaded into the application boundary.
     */
    private function ownedAppointment(
        Patient $patient,
        string $appointmentPublicId,
    ): Appointment {
        return Appointment::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', $appointmentPublicId)
            ->with(['center', 'appointmentType.healthService', 'slot'])
            ->lockForUpdate()
            ->firstOrFail();
    }
}
