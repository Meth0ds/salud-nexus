<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

/**
 * Return the result and first failure position of an audit-chain verification.
 */
final readonly class AuditChainVerification
{
    public function __construct(
        public bool $valid,
        public int $checkedEvents,
        public ?int $brokenSequence = null,
        public ?string $reason = null,
    ) {}
}
