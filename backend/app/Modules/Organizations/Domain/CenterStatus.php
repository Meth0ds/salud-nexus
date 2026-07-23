<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Domain;

/**
 * Represent whether the single center may participate in operations.
 */
enum CenterStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
