<?php

declare(strict_types=1);

namespace App\Support\Health;

/**
 * Define the dependency readiness boundary used by the health endpoint.
 */
interface ReadinessProbe
{
    /**
     * Check whether required infrastructure can currently serve traffic.
     */
    public function check(): ReadinessResult;
}
