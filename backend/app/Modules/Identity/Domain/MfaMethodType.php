<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Identify the supported MFA authenticator families.
 */
enum MfaMethodType: string
{
    case Totp = 'totp';
}
