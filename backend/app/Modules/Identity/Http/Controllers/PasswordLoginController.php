<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\AuthenticatePassword;
use App\Modules\Identity\Http\Requests\PasswordLoginRequest;
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
    ): Response {
        $authenticate->handle(
            request: $request,
            normalizedEmail: $request->normalizedEmail(),
            password: $request->password(),
        );

        return response()->noContent();
    }
}
