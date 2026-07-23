<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Enumerate capabilities advertised by an authenticated portal session.
 */
enum SessionCapability: string
{
    case Read = 'session:read';
    case Logout = 'session:logout';
}
