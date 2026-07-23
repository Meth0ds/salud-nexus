<?php

declare(strict_types=1);

namespace App\Modules\Medication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Medication\Application\DeclarePatientMedication;
use App\Modules\Medication\Application\FindPatientMedication;
use App\Modules\Medication\Application\ListPatientMedications;
use App\Modules\Medication\Application\RequestMedicationRenewal;
use App\Modules\Medication\Http\Requests\DeclareMedicationRequest;
use App\Modules\Medication\Http\Requests\RequestMedicationRenewalRequest;
use App\Modules\Medication\Http\Resources\MedicationRenewalRequestResource;
use App\Modules\Medication\Http\Resources\MedicationResource;
use App\Modules\Medication\Infrastructure\Persistence\Medication;
use App\Modules\Patients\Application\AuditPortalAction;
use App\Modules\Patients\Application\ResolvePortalPatient;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Expose audited, ownership-scoped patient medication queries and commands.
 */
final class PatientMedicationController extends Controller
{
    /**
     * Return the authenticated patient's medication summary.
     */
    public function index(
        Request $request,
        ResolvePortalPatient $resolvePatient,
        ListPatientMedications $medications,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $items = $medications->handle($patient);
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.medications.listed',
            'patient',
            $patient->public_id,
        );

        return response()->json([
            'data' => $items
                ->map(static fn (Medication $medication): array => (new MedicationResource($medication))->toArray($request))
                ->values()
                ->all(),
            'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
        ]);
    }

    /**
     * Return one owned medication without leaking foreign records.
     */
    public function show(
        Request $request,
        string $medication,
        ResolvePortalPatient $resolvePatient,
        FindPatientMedication $findMedication,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        try {
            $ownedMedication = $findMedication->handle($patient, $medication);
        } catch (ModelNotFoundException $exception) {
            $audit->denied(
                $request,
                $identity,
                $patient,
                'patient.medication.view_denied',
                'medication',
                strtolower($medication),
                ['reason_code' => 'not_found_or_not_owned'],
            );

            throw $exception;
        }
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.medication.viewed',
            'medication',
            $ownedMedication->public_id,
        );

        return response()->json([
            'data' => (new MedicationResource($ownedMedication))->toArray($request),
            'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
        ]);
    }

    /**
     * Create an idempotent patient-provided medication declaration.
     */
    public function declare(
        DeclareMedicationRequest $request,
        ResolvePortalPatient $resolvePatient,
        DeclarePatientMedication $declareMedication,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        try {
            $result = $declareMedication->handle($identity, $patient, $request->toData());
        } catch (Throwable $exception) {
            $audit->failed(
                $request,
                $identity,
                $patient,
                'patient.medication.declaration_failed',
                'patient',
                $patient->public_id,
                ['reason_code' => $exception instanceof ConflictHttpException ? 'idempotency_conflict' : 'request_rejected'],
            );

            throw $exception;
        }
        $medication = $result->resource;
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.medication.declared',
            'medication',
            $medication->public_id,
            ['idempotency_replay' => $result->replayed, 'source' => 'patient_declaration'],
        );

        return response()->json(
            data: [
                'data' => (new MedicationResource($medication))->toArray($request),
                'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
            ],
            status: 201,
            headers: ['Idempotency-Replayed' => $result->replayed ? 'true' : 'false'],
        );
    }

    /**
     * Submit an idempotent renewal request for an eligible medication.
     */
    public function renew(
        RequestMedicationRenewalRequest $request,
        string $medication,
        ResolvePortalPatient $resolvePatient,
        RequestMedicationRenewal $requestRenewal,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        try {
            $result = $requestRenewal->handle(
                $identity,
                $patient,
                strtolower($medication),
                $request->idempotencyKey(),
            );
        } catch (Throwable $exception) {
            $audit->failed(
                $request,
                $identity,
                $patient,
                'patient.medication.renewal_failed',
                'medication',
                strtolower($medication),
                [
                    'reason_code' => $exception instanceof ConflictHttpException
                        ? 'existing_request_or_conflict'
                        : 'not_found_or_not_eligible',
                ],
            );

            throw $exception;
        }
        $renewal = $result->resource;
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.medication.renewal_requested',
            'medication_renewal_request',
            $renewal->public_id,
            ['idempotency_replay' => $result->replayed],
        );

        return response()->json(
            data: [
                'data' => (new MedicationRenewalRequestResource($renewal))->toArray($request),
                'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
            ],
            status: 201,
            headers: ['Idempotency-Replayed' => $result->replayed ? 'true' : 'false'],
        );
    }

    /**
     * Resolve the authenticated web identity or fail through Laravel's auth flow.
     */
    private function identity(Request $request): IdentityAccount
    {
        $identity = $request->user('web');

        if (! $identity instanceof IdentityAccount) {
            throw new AuthenticationException(guards: ['web']);
        }

        return $identity;
    }
}
