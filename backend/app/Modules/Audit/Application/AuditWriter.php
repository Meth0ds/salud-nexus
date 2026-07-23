<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Infrastructure\Persistence\AuditEvent;

/**
 * Define the append-only persistence boundary for audit events.
 */
interface AuditWriter
{
    /**
     * Append one validated event to its organization's integrity chain.
     */
    public function record(AuditEventData $event): AuditEvent;
}
