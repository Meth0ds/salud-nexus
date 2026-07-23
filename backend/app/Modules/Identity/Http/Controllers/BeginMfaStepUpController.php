<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Application\BeginMfaStepUp;
use App\Modules\Identity\Http\Requests\MfaStepUpChallengeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Issue a purpose-bound MFA challenge for an authenticated browser session.
 */
final class BeginMfaStepUpController extends Controller
{
    /**
     * Handle the CSRF-protected step-up challenge command.
     */
    public function __invoke(
        MfaStepUpChallengeRequest $request,
        BeginMfaStepUp $beginStepUp,
    ): JsonResponse {
        $challenge = $beginStepUp->handle($request, $request->purpose());

        return response()->json([
            'data' => $challenge->publicData(),
            'meta' => [
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ], 201);
    }
}
