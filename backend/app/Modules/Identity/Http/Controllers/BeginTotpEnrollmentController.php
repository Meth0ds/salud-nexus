<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Application\BeginTotpEnrollment;
use App\Modules\Identity\Application\RequireRecentPasswordSession;
use App\Modules\Identity\Http\Requests\EmptyMfaRequest;
use Illuminate\Http\JsonResponse;

/**
 * Start a pending TOTP enrollment for a recently authenticated identity.
 */
final class BeginTotpEnrollmentController extends Controller
{
    /**
     * Handle the enrollment command without returning provisioning secrets.
     */
    public function __invoke(
        EmptyMfaRequest $request,
        RequireRecentPasswordSession $assurance,
        BeginTotpEnrollment $beginEnrollment,
    ): JsonResponse {
        $account = $assurance->handle($request);
        $requestPublicId = $request->attributes->get(AssignRequestId::ATTRIBUTE);
        $method = $beginEnrollment->handle(
            $account,
            is_string($requestPublicId) ? $requestPublicId : '',
        );

        return response()->json([
            'data' => [
                'method' => $method->type->value,
                'status' => $method->status->value,
                'expires_at' => $method->enrollment_expires_at?->format(DATE_ATOM),
                'qr_disclosure_required' => true,
            ],
            'meta' => [
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ], 201);
    }
}
