<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Identity;

use App\Shared\Domain\Identity\PublicId;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Clock\ClockInterface;

/**
 * Generate clock-aware UUIDv7 identifiers through the Ramsey UUID library.
 */
final readonly class RamseyPublicIdGenerator implements PublicIdGenerator
{
    public function __construct(private ClockInterface $clock) {}

    /**
     * Generate a new canonical UUIDv7 public identifier.
     */
    public function generate(): PublicId
    {
        return PublicId::fromString(Uuid::uuid7($this->clock->now())->toString());
    }
}
