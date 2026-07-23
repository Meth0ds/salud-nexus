<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Represent the authentication eligibility state of an identity account.
 */
enum IdentityAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
}
