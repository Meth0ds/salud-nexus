<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Application\AuditPortalAction;
use App\Modules\Patients\Application\ResolvePortalPatient;
use App\Modules\Patients\Http\Resources\PatientProfileResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Present the minimum patient profile required by the self-service portal.
 */
final class PatientProfileController extends Controller
{
    /**
     * Handle the incoming, audited profile request.
     */
    public function __invoke(
        Request $request,
        ResolvePortalPatient $resolvePatient,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.profile.viewed',
            'patient',
            $patient->public_id,
        );

        return response()->json([
            'data' => (new PatientProfileResource($patient))->toArray($request),
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
