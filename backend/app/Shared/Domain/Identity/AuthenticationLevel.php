<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Represent ascending authentication assurance levels.
 */
enum AuthenticationLevel: int
{
    case Aal1 = 1;
    case Aal2 = 2;
    case Aal3 = 3;
}
