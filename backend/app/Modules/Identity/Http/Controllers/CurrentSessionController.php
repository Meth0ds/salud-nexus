<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Application\BrowserSession;
use App\Modules\Identity\Domain\SessionCapability;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Present the authenticated session without exposing account credentials.
 */
final class CurrentSessionController extends Controller
{
    /**
     * Handle the current-session request and verify server-owned session metadata.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user('web');
        $method = $request->session()->get(BrowserSession::METHOD);
        $level = $request->session()->get(BrowserSession::LEVEL);
        $authenticatedAt = $request->session()->get(BrowserSession::AUTHENTICATED_AT);
        $expectedLevel = match ($method) {
            'password' => 1,
            'password+totp', 'password+recovery' => 2,
            default => null,
        };

        if (
            ! $account instanceof IdentityAccount
            || $expectedLevel === null
            || $level !== $expectedLevel
            || ! is_string($authenticatedAt)
        ) {
            throw new AuthenticationException(guards: ['web']);
        }

        return response()->json([
            'data' => [
                'authenticated' => true,
                'identity' => [
                    'id' => $account->public_id,
                    'display_name' => $account->display_name,
                ],
                'authentication' => [
                    'method' => $method,
                    'level' => 'aal'.$level,
                    'authenticated_at' => $authenticatedAt,
                ],
                'capabilities' => array_map(
                    static fn (SessionCapability $capability): string => $capability->value,
                    SessionCapability::cases(),
                ),
            ],
            'meta' => [
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ]);
    }
}
