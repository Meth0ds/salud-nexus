<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\DiscloseTotpQrCode;
use App\Modules\Identity\Application\RequireRecentPasswordSession;
use App\Modules\Identity\Http\Requests\EmptyMfaRequest;
use Illuminate\Http\Response;

/**
 * Return and consume the authenticated identity's pending TOTP QR disclosure.
 */
final class DiscloseTotpQrCodeController extends Controller
{
    /**
     * Handle the CSRF-protected one-use SVG disclosure command.
     */
    public function __invoke(
        EmptyMfaRequest $request,
        RequireRecentPasswordSession $assurance,
        DiscloseTotpQrCode $discloseQrCode,
    ): Response {
        $account = $assurance->handle($request);
        $svg = $discloseQrCode->handle($account);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="totp-enrollment.svg"',
        ]);
    }
}
