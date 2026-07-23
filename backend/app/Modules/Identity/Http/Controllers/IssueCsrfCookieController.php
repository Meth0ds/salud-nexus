<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Complete Sanctum's CSRF bootstrap after middleware issues the cookie.
 */
final class IssueCsrfCookieController extends Controller
{
    /**
     * Return an empty response; cookie creation is handled by middleware.
     */
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
