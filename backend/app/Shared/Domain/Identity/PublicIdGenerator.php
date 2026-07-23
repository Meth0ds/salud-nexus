<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Define the replaceable public identifier generation boundary.
 */
interface PublicIdGenerator
{
    /**
     * Generate a new time-ordered public identifier.
     */
    public function generate(): PublicId;
}
