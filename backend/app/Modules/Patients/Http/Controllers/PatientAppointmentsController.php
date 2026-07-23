<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Application\AuditPortalAction;
use App\Modules\Patients\Application\ResolvePortalPatient;
use App\Modules\Patients\Http\Requests\BookAppointmentRequest;
use App\Modules\Patients\Http\Requests\CancelAppointmentRequest;
use App\Modules\Patients\Http\Requests\ListAppointmentsRequest;
use App\Modules\Patients\Http\Requests\RescheduleAppointmentRequest;
use App\Modules\Patients\Http\Resources\AppointmentResource;
use App\Modules\Scheduling\Application\AppointmentVersionTag;
use App\Modules\Scheduling\Application\BookPatientAppointment;
use App\Modules\Scheduling\Application\CancelPatientAppointment;
use App\Modules\Scheduling\Application\FindPatientAppointment;
use App\Modules\Scheduling\Application\ListPatientAppointments;
use App\Modules\Scheduling\Application\ReschedulePatientAppointment;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Expose patient-owned appointment queries and commands over the portal API.
 *
 * The controller keeps transport, audit, and HTTP validator concerns at the
 * edge while delegating business invariants to scheduling application services.
 */
final class PatientAppointmentsController extends Controller
{
    /**
     * Return a paginated, audited list of the authenticated patient's appointments.
     */
    public function index(
        ListAppointmentsRequest $request,
        ResolvePortalPatient $resolvePatient,
        ListPatientAppointments $appointments,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $page = $appointments->handle(
            $patient,
            $request->appointmentScope(),
            $request->perPage(),
        );
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.appointments.listed',
            'patient',
            $patient->public_id,
        );

        return response()->json([
            'data' => collect($page->items())
                ->map(static fn ($appointment): array => (new AppointmentResource($appointment))->toArray($request))
                ->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ]);
    }

    /**
     * Return one owned appointment and its strong concurrency validator.
     */
    public function show(
        Request $request,
        string $appointment,
        ResolvePortalPatient $resolvePatient,
        FindPatientAppointment $findAppointment,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        try {
            $ownedAppointment = $findAppointment->handle($patient, strtolower($appointment));
        } catch (ModelNotFoundException $exception) {
            $audit->denied(
                $request,
                $identity,
                $patient,
                'patient.appointment.view_denied',
                'appointment',
                strtolower($appointment),
                ['reason_code' => 'not_found_or_not_owned'],
            );

            throw $exception;
        }
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.appointment.viewed',
            'appointment',
            $ownedAppointment->public_id,
        );

        return response()->json(
            data: [
                'data' => (new AppointmentResource($ownedAppointment))->toArray($request),
                'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
            ],
            headers: ['ETag' => AppointmentVersionTag::format($ownedAppointment->version)],
        );
    }

    /**
     * Book an appointment and expose whether the response was replayed.
     */
    public function store(
        BookAppointmentRequest $request,
        ResolvePortalPatient $resolvePatient,
        BookPatientAppointment $bookAppointment,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $data = $request->toData();
        try {
            $result = $bookAppointment->handle($identity, $patient, $data);
        } catch (Throwable $exception) {
            $audit->failed(
                $request,
                $identity,
                $patient,
                'patient.appointment.booking_failed',
                'appointment_type',
                strtolower($data->appointmentTypeId),
                [
                    'reason_code' => $exception instanceof ConflictHttpException
                        ? 'availability_conflict'
                        : 'request_rejected',
                ],
            );

            throw $exception;
        }
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.appointment.booked',
            'appointment',
            $result->appointment->public_id,
            ['idempotency_replay' => $result->replayed],
        );

        return response()->json(
            data: [
                'data' => (new AppointmentResource($result->appointment))->toArray($request),
                'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
            ],
            status: 201,
            headers: [
                'ETag' => AppointmentVersionTag::format($result->appointment->version),
                'Idempotency-Replayed' => $result->replayed ? 'true' : 'false',
            ],
        );
    }

    /**
     * Cancel an owned appointment while recording success or denial in the audit chain.
     */
    public function cancel(
        CancelAppointmentRequest $request,
        string $appointment,
        ResolvePortalPatient $resolvePatient,
        CancelPatientAppointment $cancelAppointment,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $appointment = strtolower($appointment);
        $data = $request->toData();

        try {
            $result = $cancelAppointment->handle($identity, $patient, $appointment, $data);
        } catch (ModelNotFoundException $exception) {
            $audit->denied(
                $request,
                $identity,
                $patient,
                'patient.appointment.cancellation_denied',
                'appointment',
                $appointment,
                ['reason_code' => 'not_found_or_not_owned'],
            );

            throw $exception;
        } catch (Throwable $exception) {
            $audit->failed(
                $request,
                $identity,
                $patient,
                'patient.appointment.cancellation_failed',
                'appointment',
                $appointment,
                [
                    'reason_code' => $exception instanceof ConflictHttpException
                        ? 'change_conflict'
                        : 'request_rejected',
                ],
            );

            throw $exception;
        }

        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.appointment.cancelled',
            'appointment',
            $result->appointment->public_id,
            ['idempotency_replay' => $result->replayed],
        );

        return response()->json(
            data: [
                'data' => (new AppointmentResource($result->appointment))->toArray($request),
                'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
            ],
            headers: [
                'ETag' => AppointmentVersionTag::format($result->appointment->version),
                'Idempotency-Replayed' => $result->replayed ? 'true' : 'false',
            ],
        );
    }

    /**
     * Reschedule an owned appointment while preserving conflict-safe HTTP semantics.
     */
    public function reschedule(
        RescheduleAppointmentRequest $request,
        string $appointment,
        ResolvePortalPatient $resolvePatient,
        ReschedulePatientAppointment $rescheduleAppointment,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $appointment = strtolower($appointment);
        $data = $request->toData();

        try {
            $result = $rescheduleAppointment->handle($identity, $patient, $appointment, $data);
        } catch (ModelNotFoundException $exception) {
            $audit->denied(
                $request,
                $identity,
                $patient,
                'patient.appointment.reschedule_denied',
                'appointment',
                $appointment,
                ['reason_code' => 'appointment_or_slot_not_found'],
            );

            throw $exception;
        } catch (Throwable $exception) {
            $audit->failed(
                $request,
                $identity,
                $patient,
                'patient.appointment.reschedule_failed',
                'appointment',
                $appointment,
                [
                    'reason_code' => $exception instanceof ConflictHttpException
                        ? 'change_conflict'
                        : 'request_rejected',
                ],
            );

            throw $exception;
        }

        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.appointment.rescheduled',
            'appointment',
            $result->appointment->public_id,
            ['idempotency_replay' => $result->replayed],
        );

        return response()->json(
            data: [
                'data' => (new AppointmentResource($result->appointment))->toArray($request),
                'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
            ],
            headers: [
                'ETag' => AppointmentVersionTag::format($result->appointment->version),
                'Idempotency-Replayed' => $result->replayed ? 'true' : 'false',
            ],
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
