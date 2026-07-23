<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\VerifyMfaChallenge;
use App\Modules\Identity\Http\Requests\MfaChallengeVerificationRequest;
use Illuminate\Http\Response;

/**
 * Complete a guest MFA login challenge without disclosing factor details.
 */
final class VerifyMfaChallengeController extends Controller
{
    /**
     * Handle one CSRF-protected challenge verification command.
     */
    public function __invoke(
        MfaChallengeVerificationRequest $request,
        VerifyMfaChallenge $verifyChallenge,
    ): Response {
        $verifyChallenge->handle(
            request: $request,
            challengeId: $request->challengeId(),
            verificationMethod: $request->verificationMethod(),
            code: $request->code(),
        );

        return response()->noContent();
    }
}
