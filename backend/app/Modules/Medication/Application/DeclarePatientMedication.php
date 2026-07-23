<?php

declare(strict_types=1);

namespace App\Modules\Medication\Application;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Medication\Application\Ports\MedicationEventPublisher;
use App\Modules\Medication\Domain\MedicationDeclared;
use App\Modules\Medication\Domain\MedicationSource;
use App\Modules\Medication\Domain\MedicationStatus;
use App\Modules\Medication\Infrastructure\Persistence\Medication;
use App\Modules\Medication\Infrastructure\Persistence\MedicationIdempotencyRequest;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Persist a patient-provided medication declaration exactly once.
 */
final readonly class DeclarePatientMedication
{
    private const OPERATION = 'patient.medication.declare';

    public function __construct(
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
        private MedicationEventPublisher $events,
    ) {}

    /**
     * Create the declaration and idempotency result in one transaction.
     *
     * @return MedicationMutationResult<Medication>
     */
    public function handle(
        IdentityAccount $account,
        Patient $patient,
        DeclareMedicationData $data,
    ): MedicationMutationResult {
        $requestHash = $data->requestHash();
        $keyHash = hash('sha256', $data->idempotencyKey);

        try {
            /**
             * Preserve the generic resource type returned by the transaction.
             *
             * @var MedicationMutationResult<Medication> $result
             */
            $result = DB::transaction(function () use (
                $account,
                $patient,
                $data,
                $requestHash,
                $keyHash,
            ): MedicationMutationResult {
                $existing = $this->idempotencyRecord($account, $keyHash, true);
                if ($existing instanceof MedicationIdempotencyRequest) {
                    return $this->replay($existing, $patient, $requestHash);
                }

                $idempotency = MedicationIdempotencyRequest::query()->create([
                    'identity_account_id' => $account->id,
                    'operation' => self::OPERATION,
                    'idempotency_key_hash' => $keyHash,
                    'request_hash' => $requestHash,
                    'status' => 'processing',
                    'expires_at' => $this->clock->now()->modify('+24 hours'),
                ]);
                $medication = Medication::query()->create([
                    'organization_id' => $patient->organization_id,
                    'patient_id' => $patient->id,
                    'public_id' => $this->publicIds->generate()->toString(),
                    'source' => MedicationSource::PatientDeclaration,
                    'name' => $data->name,
                    'presentation' => $data->presentation,
                    'schedule_label' => $data->scheduleLabel,
                    'status' => MedicationStatus::Active,
                    'recorded_by_identity_public_id' => null,
                ]);
                $idempotency->forceFill([
                    'status' => 'completed',
                    'resource_type' => 'medication',
                    'resource_public_id' => $medication->public_id,
                    'completed_at' => $this->clock->now(),
                ])->save();

                return new MedicationMutationResult($medication, false);
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->idempotencyRecord($account, $keyHash, false);
            if ($existing instanceof MedicationIdempotencyRequest) {
                return $this->replay($existing, $patient, $requestHash);
            }

            throw new ConflictHttpException(previous: $exception);
        }

        if (! $result->replayed) {
            /**
             * Narrow the generic mutation resource before publishing the event.
             *
             * @var Medication $medication
             */
            $medication = $result->resource;
            $this->events->publish(new MedicationDeclared(
                medicationId: $medication->public_id,
                organizationId: $patient->organization->public_id,
                actorIdentityId: $account->public_id,
                occurredAt: $this->clock->now(),
            ));
        }

        return $result;
    }

    /**
     * Find the hashed actor-scoped idempotency record, optionally locking it.
     */
    private function idempotencyRecord(
        IdentityAccount $account,
        string $keyHash,
        bool $lock,
    ): ?MedicationIdempotencyRequest {
        $query = MedicationIdempotencyRequest::query()
            ->where('identity_account_id', $account->id)
            ->where('operation', self::OPERATION)
            ->where('idempotency_key_hash', $keyHash);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Replay a completed declaration after proving payload equivalence.
     *
     * @return MedicationMutationResult<Medication>
     */
    private function replay(
        MedicationIdempotencyRequest $request,
        Patient $patient,
        string $requestHash,
    ): MedicationMutationResult {
        if (
            ! hash_equals($request->request_hash, $requestHash)
            || $request->status !== 'completed'
            || $request->resource_type !== 'medication'
            || ! is_string($request->resource_public_id)
        ) {
            throw new ConflictHttpException;
        }

        $medication = Medication::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', $request->resource_public_id)
            ->firstOrFail();

        return new MedicationMutationResult($medication, true);
    }
}
