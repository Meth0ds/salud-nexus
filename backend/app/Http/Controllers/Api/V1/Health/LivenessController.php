<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Health;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Confirm that the PHP application process can serve requests.
 */
final class LivenessController extends Controller
{
    /**
     * Handle the infrastructure liveness probe without external dependencies.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'alive',
                'checks' => ['application' => 'ok'],
            ],
            'meta' => [
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ]);
    }
}
