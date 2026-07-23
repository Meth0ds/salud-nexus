<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Application\Ports\DomainEventPublisher;
use App\Modules\Scheduling\Domain\AppointmentCancelled;
use App\Modules\Scheduling\Domain\AppointmentChangeTransition;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentChange;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlotAllocation;
use App\Modules\Scheduling\Infrastructure\Persistence\IdempotencyRequest;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Cancel an owned appointment without exposing or partially releasing its slot.
 */
final readonly class CancelPatientAppointment
{
    private const OPERATION = 'api.v1.patient.appointments.cancellations.store';

    public function __construct(
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
        private DomainEventPublisher $events,
        private PatientAppointmentChangePolicy $policy,
        private AppointmentChangeIdempotency $idempotency,
        private AppointmentChangeReplay $replay,
    ) {}

    /**
     * Cancel one patient-owned appointment using optimistic concurrency.
     *
     * Slot release, snapshot mutation, history creation, and idempotency
     * completion commit atomically. The domain event is emitted only afterward.
     */
    public function handle(
        IdentityAccount $account,
        Patient $patient,
        string $appointmentPublicId,
        CancelAppointmentData $data,
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

                $allocation = AppointmentSlotAllocation::query()
                    ->where('organization_id', $patient->organization_id)
                    ->where('appointment_id', $appointment->id)
                    ->where('slot_id', $appointment->slot_id)
                    ->lockForUpdate()
                    ->first();

                if (! $allocation instanceof AppointmentSlotAllocation) {
                    throw new ConflictHttpException;
                }

                $fromVersion = $appointment->version;
                $occurredAt = $this->clock->now();

                // Deletion and state transition share the transaction, so failure restores ownership.
                $allocation->delete();
                $appointment->forceFill([
                    'status' => AppointmentStatus::Cancelled,
                    'version' => $fromVersion + 1,
                ])->save();
                $appointment->setRelation('activeSlotAllocation', null);

                $change = AppointmentChange::query()->create([
                    'organization_id' => $patient->organization_id,
                    'appointment_id' => $appointment->id,
                    'identity_account_id' => $account->id,
                    'public_id' => $this->publicIds->generate()->toString(),
                    'transition' => AppointmentChangeTransition::Cancelled,
                    'from_status' => AppointmentStatus::Scheduled,
                    'to_status' => AppointmentStatus::Cancelled,
                    'from_slot_id' => $appointment->slot_id,
                    'to_slot_id' => null,
                    'reason_code' => $data->reason,
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

                return new AppointmentChangeResult($appointment, false);
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
            $this->events->publish(new AppointmentCancelled(
                appointmentId: $result->appointment->public_id,
                organizationId: $patient->organization->public_id,
                actorIdentityId: $account->public_id,
                reason: $data->reason,
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
                AppointmentChangeTransition::Cancelled,
            ),
            true,
        );
    }

    /**
     * Lock the appointment while enforcing both organization and patient scope.
     *
     * Returning the same not-found outcome for absent and foreign records avoids
     * leaking whether another patient owns a supplied public identifier.
     */
    private function ownedAppointment(
        Patient $patient,
        string $appointmentPublicId,
    ): Appointment {
        return Appointment::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', $appointmentPublicId)
            ->with(['center', 'appointmentType.healthService'])
            ->lockForUpdate()
            ->firstOrFail();
    }
}
