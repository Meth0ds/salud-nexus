<?php

declare(strict_types=1);

namespace App\Support\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verify database connectivity without logging credentials or query details.
 */
final class DatabaseReadinessProbe implements ReadinessProbe
{
    /**
     * Run the dependency check and return a sanitized readiness result.
     */
    public function check(): ReadinessResult
    {
        try {
            DB::connection()->select('select 1');

            return ReadinessResult::ready();
        } catch (Throwable $exception) {
            Log::warning('Readiness database check failed.', [
                'exception_type' => $exception::class,
            ]);

            return ReadinessResult::notReady('database');
        }
    }
}
