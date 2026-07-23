<?php

declare(strict_types=1);

namespace App\Modules\Medication\Application;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Medication\Application\Ports\MedicationEventPublisher;
use App\Modules\Medication\Domain\MedicationRenewalRequested;
use App\Modules\Medication\Domain\MedicationSource;
use App\Modules\Medication\Domain\MedicationStatus;
use App\Modules\Medication\Domain\RenewalRequestStatus;
use App\Modules\Medication\Infrastructure\Persistence\Medication;
use App\Modules\Medication\Infrastructure\Persistence\MedicationIdempotencyRequest;
use App\Modules\Medication\Infrastructure\Persistence\MedicationRenewalRequest;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Submit one renewal request for an eligible professional medication record.
 */
final readonly class RequestMedicationRenewal
{
    private const OPERATION_PREFIX = 'patient.medication.renewal:';

    public function __construct(
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
        private MedicationEventPublisher $events,
    ) {}

    /**
     * Persist an eligible renewal request exactly once.
     *
     * @return MedicationMutationResult<MedicationRenewalRequest>
     */
    public function handle(
        IdentityAccount $account,
        Patient $patient,
        string $medicationPublicId,
        string $idempotencyKey,
    ): MedicationMutationResult {
        $operation = self::OPERATION_PREFIX.strtolower($medicationPublicId);
        $requestHash = hash('sha256', json_encode([
            'medication_id' => strtolower($medicationPublicId),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $keyHash = hash('sha256', $idempotencyKey);

        try {
            /**
             * Preserve the generic resource type returned by the transaction.
             *
             * @var MedicationMutationResult<MedicationRenewalRequest> $result
             */
            $result = DB::transaction(function () use (
                $account,
                $patient,
                $medicationPublicId,
                $operation,
                $requestHash,
                $keyHash,
            ): MedicationMutationResult {
                $existing = $this->idempotencyRecord($account, $operation, $keyHash, true);
                if ($existing instanceof MedicationIdempotencyRequest) {
                    return $this->replay($existing, $patient, $requestHash);
                }

                $medication = Medication::query()
                    ->where('organization_id', $patient->organization_id)
                    ->where('patient_id', $patient->id)
                    ->where('public_id', strtolower($medicationPublicId))
                    ->where('source', MedicationSource::ProfessionalRecord->value)
                    ->where('status', MedicationStatus::Active->value)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (MedicationRenewalRequest::query()
                    ->where('organization_id', $patient->organization_id)
                    ->where('patient_id', $patient->id)
                    ->where('medication_id', $medication->id)
                    ->where('status', RenewalRequestStatus::Submitted->value)
                    ->exists()) {
                    throw new ConflictHttpException;
                }

                $idempotency = MedicationIdempotencyRequest::query()->create([
                    'identity_account_id' => $account->id,
                    'operation' => $operation,
                    'idempotency_key_hash' => $keyHash,
                    'request_hash' => $requestHash,
                    'status' => 'processing',
                    'expires_at' => $this->clock->now()->modify('+24 hours'),
                ]);
                $renewal = MedicationRenewalRequest::query()->create([
                    'organization_id' => $patient->organization_id,
                    'patient_id' => $patient->id,
                    'medication_id' => $medication->id,
                    'public_id' => $this->publicIds->generate()->toString(),
                    'status' => RenewalRequestStatus::Submitted,
                    'requested_at' => $this->clock->now(),
                ]);
                $renewal->setRelation('medication', $medication);
                $idempotency->forceFill([
                    'status' => 'completed',
                    'resource_type' => 'medication_renewal_request',
                    'resource_public_id' => $renewal->public_id,
                    'completed_at' => $this->clock->now(),
                ])->save();

                return new MedicationMutationResult($renewal, false);
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->idempotencyRecord($account, $operation, $keyHash, false);
            if ($existing instanceof MedicationIdempotencyRequest) {
                return $this->replay($existing, $patient, $requestHash);
            }

            throw new ConflictHttpException(previous: $exception);
        }

        if (! $result->replayed) {
            /**
             * Narrow the generic mutation resource before publishing the event.
             *
             * @var MedicationRenewalRequest $renewal
             */
            $renewal = $result->resource;
            $this->events->publish(new MedicationRenewalRequested(
                requestId: $renewal->public_id,
                medicationId: $renewal->medication->public_id,
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
        string $operation,
        string $keyHash,
        bool $lock,
    ): ?MedicationIdempotencyRequest {
        $query = MedicationIdempotencyRequest::query()
            ->where('identity_account_id', $account->id)
            ->where('operation', $operation)
            ->where('idempotency_key_hash', $keyHash);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Replay a completed renewal request after proving payload equivalence.
     *
     * @return MedicationMutationResult<MedicationRenewalRequest>
     */
    private function replay(
        MedicationIdempotencyRequest $request,
        Patient $patient,
        string $requestHash,
    ): MedicationMutationResult {
        if (
            ! hash_equals($request->request_hash, $requestHash)
            || $request->status !== 'completed'
            || $request->resource_type !== 'medication_renewal_request'
            || ! is_string($request->resource_public_id)
        ) {
            throw new ConflictHttpException;
        }

        $renewal = MedicationRenewalRequest::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', $request->resource_public_id)
            ->with('medication')
            ->firstOrFail();

        return new MedicationMutationResult($renewal, true);
    }
}
