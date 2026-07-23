<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Domain;

/**
 * Represent whether the owning organization may participate in operations.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
