<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\TerminateBrowserSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Terminate the authenticated browser session through the identity boundary.
 */
final class LogoutController extends Controller
{
    /**
     * Handle the logout request and return an empty success response.
     */
    public function __invoke(
        Request $request,
        TerminateBrowserSession $terminate,
    ): Response {
        $terminate->handle($request);

        return response()->noContent();
    }
}
