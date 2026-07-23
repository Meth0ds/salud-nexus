<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Represent the normalized outcome of an identity security event.
 */
enum SecurityEventOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Denied = 'denied';
}
