<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Application\AuditPortalAction;
use App\Modules\Patients\Application\ResolvePortalPatient;
use App\Modules\Patients\Http\Resources\AppointmentResource;
use App\Modules\Scheduling\Application\GetPatientDashboard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Present an audited scheduling summary for the authenticated portal patient.
 */
final class PatientDashboardController extends Controller
{
    /**
     * Handle the incoming patient dashboard request.
     */
    public function __invoke(
        Request $request,
        ResolvePortalPatient $resolvePatient,
        GetPatientDashboard $dashboard,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $data = $dashboard->handle($patient);
        $nextAppointment = $data['next_appointment'];
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.dashboard.viewed',
            'patient',
            $patient->public_id,
        );

        return response()->json([
            'data' => [
                'upcoming_appointments_count' => $data['upcoming_appointments_count'],
                'next_appointment' => $nextAppointment === null
                    ? null
                    : (new AppointmentResource($nextAppointment))->toArray($request),
                'available_appointment_types_count' => $data['available_appointment_types_count'],
            ],
            'meta' => ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)],
        ]);
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
