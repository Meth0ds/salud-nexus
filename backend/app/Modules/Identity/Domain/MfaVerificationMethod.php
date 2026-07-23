<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Identify a factor accepted by an MFA challenge.
 */
enum MfaVerificationMethod: string
{
    case Totp = 'totp';
    case Recovery = 'recovery';
}
