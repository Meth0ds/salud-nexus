<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Application\AuthenticatePassword;
use App\Modules\Identity\Http\Requests\PasswordLoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Translate a validated password login request into a stateful session command.
 */
final class PasswordLoginController extends Controller
{
    /**
     * Handle the password login request without returning identity secrets.
     */
    public function __invoke(
        PasswordLoginRequest $request,
        AuthenticatePassword $authenticate,
    ): Response|JsonResponse {
        $outcome = $authenticate->handle(
            request: $request,
            normalizedEmail: $request->normalizedEmail(),
            password: $request->password(),
        );

        if ($outcome->requiresMfa() && $outcome->challenge !== null) {
            return response()->json([
                'data' => $outcome->challenge->publicData(),
                'meta' => [
                    'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
                ],
            ], 202);
        }

        return response()->noContent();
    }
}
