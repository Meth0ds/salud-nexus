<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

/**
 * Represent the security-relevant result of an audited action.
 */
enum AuditOutcome: string
{
    case Succeeded = 'succeeded';
    case Denied = 'denied';
    case Failed = 'failed';
}
