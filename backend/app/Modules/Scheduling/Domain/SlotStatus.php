<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

/**
 * Represent whether a slot may participate in a new booking or reschedule.
 */
enum SlotStatus: string
{
    case Open = 'open';
    case Blocked = 'blocked';
}
