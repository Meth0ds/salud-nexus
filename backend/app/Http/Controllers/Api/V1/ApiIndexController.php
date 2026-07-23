<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Advertise the stable API name, version, and availability state.
 */
final class ApiIndexController extends Controller
{
    /**
     * Handle the incoming API discovery request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'name' => config('api.name'),
                'version' => config('api.version'),
                'status' => 'available',
            ],
            'meta' => [
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ]);
    }
}
