<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Application\AuditPortalAction;
use App\Modules\Patients\Application\ResolvePortalPatient;
use App\Modules\Patients\Http\Resources\BookingAppointmentTypeResource;
use App\Modules\Scheduling\Application\GetBookingOptions;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Clock\ClockInterface;

/**
 * Return appointment types and currently available slots for the portal patient.
 */
final class BookingOptionsController extends Controller
{
    /**
     * Handle the authenticated booking-options request.
     */
    public function __invoke(
        Request $request,
        ResolvePortalPatient $resolvePatient,
        GetBookingOptions $bookingOptions,
        ClockInterface $clock,
        AuditPortalAction $audit,
    ): JsonResponse {
        $identity = $this->identity($request);
        $patient = $resolvePatient->handle($identity);
        $types = $bookingOptions->handle($patient);
        $audit->succeeded(
            $request,
            $identity,
            $patient,
            'patient.booking_options.viewed',
            'patient',
            $patient->public_id,
        );

        return response()->json([
            'data' => [
                'appointment_types' => $types
                    ->map(static fn ($type): array => (new BookingAppointmentTypeResource($type))->toArray($request))
                    ->values()
                    ->all(),
            ],
            'meta' => [
                'generated_at' => $clock->now()->format(DATE_ATOM),
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
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
