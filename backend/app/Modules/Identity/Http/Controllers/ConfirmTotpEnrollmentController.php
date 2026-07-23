<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Application\ConfirmTotpEnrollment;
use App\Modules\Identity\Application\RequireRecentPasswordSession;
use App\Modules\Identity\Http\Requests\TotpEnrollmentConfirmationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Activate a pending TOTP method and disclose recovery codes once.
 */
final class ConfirmTotpEnrollmentController extends Controller
{
    /**
     * Handle confirmation and return the ephemeral recovery code set.
     */
    public function __invoke(
        TotpEnrollmentConfirmationRequest $request,
        RequireRecentPasswordSession $assurance,
        ConfirmTotpEnrollment $confirmEnrollment,
    ): JsonResponse {
        $account = $assurance->handle($request);
        $recoveryCodes = $confirmEnrollment->handle(
            $request,
            $account,
            $request->code(),
        );

        return response()->json([
            'data' => [
                'method' => 'totp',
                'status' => 'active',
                'recovery_codes' => $recoveryCodes,
            ],
            'meta' => [
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ]);
    }
}
