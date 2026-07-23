<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Present the minimum MFA state required by the authenticated settings UI.
 */
final class MfaStatusController extends Controller
{
    /**
     * Return MFA status without secret, hash, or recovery code disclosure.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user('web');

        if (! $account instanceof IdentityAccount) {
            throw new AuthenticationException(guards: ['web']);
        }

        $method = IdentityMfaMethod::query()
            ->where('identity_account_id', $account->id)
            ->where('type', MfaMethodType::Totp->value)
            ->first();
        $active = $method instanceof IdentityMfaMethod
            && $method->status === MfaMethodStatus::Active;

        return response()->json([
            'data' => [
                'enabled' => $active,
                'method' => $method?->type->value,
                'status' => $method?->status->value,
                'confirmed_at' => $method?->confirmed_at?->format(DATE_ATOM),
                'recovery_codes_remaining' => $active
                    ? $method->recoveryCodes()->whereNull('used_at')->count()
                    : 0,
            ],
            'meta' => [
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ]);
    }
}
