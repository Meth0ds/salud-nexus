<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Organizations\Domain\CenterStatus;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Scheduling\Application\Ports\DomainEventPublisher;
use App\Modules\Scheduling\Domain\AppointmentBooked;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Domain\SlotStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlot;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentSlotAllocation;
use App\Modules\Scheduling\Infrastructure\Persistence\AppointmentType;
use App\Modules\Scheduling\Infrastructure\Persistence\IdempotencyRequest;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Book an available slot for the authenticated patient exactly once.
 */
final readonly class BookPatientAppointment
{
    private const ROUTE = 'api.v1.patient.appointments.store';

    public function __construct(
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
        private DomainEventPublisher $events,
    ) {}

    /**
     * Persist the appointment, active allocation, and idempotency result atomically.
     *
     * The domain event is dispatched only after the database transaction commits,
     * preventing downstream handlers from observing a rolled-back booking.
     */
    public function handle(
        IdentityAccount $account,
        Patient $patient,
        BookAppointmentData $data,
    ): BookAppointmentResult {
        $requestHash = $data->requestHash();

        try {
            $result = DB::transaction(function () use ($account, $patient, $data, $requestHash): BookAppointmentResult {
                $existing = $this->idempotencyRecord($account, $data->idempotencyKey, true);

                if ($existing instanceof IdempotencyRequest) {
                    return $this->replay($existing, $patient, $requestHash);
                }

                $idempotency = IdempotencyRequest::query()->create([
                    'identity_account_id' => $account->id,
                    'route' => self::ROUTE,
                    'idempotency_key' => $data->idempotencyKey,
                    'request_hash' => $requestHash,
                    'status' => 'processing',
                    'expires_at' => $this->clock->now()->modify('+24 hours'),
                ]);

                $type = AppointmentType::query()
                    ->where('organization_id', $patient->organization_id)
                    ->where('public_id', strtolower($data->appointmentTypeId))
                    ->where('is_active', true)
                    ->whereHas('healthService', static fn ($query) => $query->where('is_active', true))
                    ->firstOrFail();

                $slot = AppointmentSlot::query()
                    ->where('organization_id', $patient->organization_id)
                    ->where('appointment_type_id', $type->id)
                    ->where('public_id', strtolower($data->slotId))
                    ->where('status', SlotStatus::Open->value)
                    ->where('starts_at', '>=', $this->clock->now())
                    ->whereHas('center', static fn ($query) => $query->where(
                        'status',
                        CenterStatus::Active->value,
                    ))
                    ->with(['center', 'appointmentType.healthService'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (AppointmentSlotAllocation::query()->where('slot_id', $slot->id)->exists()) {
                    throw new ConflictHttpException;
                }

                $appointment = Appointment::query()->create([
                    'organization_id' => $patient->organization_id,
                    'patient_id' => $patient->id,
                    'center_id' => $slot->center_id,
                    'appointment_type_id' => $type->id,
                    'slot_id' => $slot->id,
                    'public_id' => $this->publicIds->generate()->toString(),
                    'status' => AppointmentStatus::Scheduled,
                    'attendance_mode' => $type->attendance_mode,
                    'center_timezone' => $slot->center->timezone,
                    'location_label' => $slot->location_label,
                    'professional_display_name' => $slot->professional_display_name,
                    'starts_at' => $slot->starts_at,
                    'ends_at' => $slot->ends_at,
                ]);
                // Refresh database defaults such as the optimistic-lock version before responding.
                $appointment->refresh();
                $appointment->setRelations([
                    'center' => $slot->center,
                    'appointmentType' => $type,
                ]);

                // The database uniqueness constraint is the final guard against concurrent bookings.
                AppointmentSlotAllocation::query()->create([
                    'organization_id' => $patient->organization_id,
                    'appointment_id' => $appointment->id,
                    'slot_id' => $slot->id,
                ]);

                $idempotency->forceFill([
                    'status' => 'completed',
                    'response_status' => 201,
                    'resource_public_id' => $appointment->public_id,
                    'completed_at' => $this->clock->now(),
                ])->save();

                return new BookAppointmentResult($appointment, false);
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->idempotencyRecord($account, $data->idempotencyKey, false);

            if ($existing instanceof IdempotencyRequest) {
                return $this->replay($existing, $patient, $requestHash);
            }

            throw new ConflictHttpException(previous: $exception);
        }

        if (! $result->replayed) {
            $this->events->publish(new AppointmentBooked(
                appointmentId: $result->appointment->public_id,
                organizationId: $patient->organization->public_id,
                actorIdentityId: $account->public_id,
                occurredAt: $this->clock->now(),
            ));
        }

        return $result;
    }

    /**
     * Find the actor- and route-scoped idempotency record, optionally locking it.
     */
    private function idempotencyRecord(
        IdentityAccount $account,
        string $key,
        bool $lock,
    ): ?IdempotencyRequest {
        $query = IdempotencyRequest::query()
            ->where('identity_account_id', $account->id)
            ->where('route', self::ROUTE)
            ->where('idempotency_key', $key);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Replay a completed booking after proving that its payload is identical.
     */
    private function replay(
        IdempotencyRequest $request,
        Patient $patient,
        string $requestHash,
    ): BookAppointmentResult {
        if (! hash_equals($request->request_hash, $requestHash)) {
            throw new ConflictHttpException;
        }

        if ($request->status !== 'completed' || ! is_string($request->resource_public_id)) {
            throw new ConflictHttpException;
        }

        $appointment = Appointment::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', $request->resource_public_id)
            ->with(['center', 'appointmentType.healthService'])
            ->firstOrFail();

        return new BookAppointmentResult($appointment, true);
    }
}
