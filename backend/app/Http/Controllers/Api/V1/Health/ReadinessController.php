<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Health;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignRequestId;
use App\Support\Health\ReadinessProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Report whether required infrastructure is ready to receive traffic.
 */
final class ReadinessController extends Controller
{
    public function __construct(private readonly ReadinessProbe $probe) {}

    /**
     * Handle the readiness probe and return 503 when a dependency is unavailable.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->probe->check();

        if (! $result->ready) {
            throw new ServiceUnavailableHttpException;
        }

        return response()->json([
            'data' => [
                'status' => 'ready',
                'checks' => $result->checks,
            ],
            'meta' => [
                'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE),
            ],
        ]);
    }
}
