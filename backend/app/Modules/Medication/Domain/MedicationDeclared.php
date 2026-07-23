<?php

declare(strict_types=1);

namespace App\Modules\Medication\Domain;

use DateTimeImmutable;

/**
 * Carry the minimum non-clinical data required after a declaration commits.
 */
final readonly class MedicationDeclared
{
    public function __construct(
        public string $medicationId,
        public string $organizationId,
        public string $actorIdentityId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
