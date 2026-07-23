<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Represent the controlled lifecycle of an MFA method.
 */
enum MfaMethodStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';
}
