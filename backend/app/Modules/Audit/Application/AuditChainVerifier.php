<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Shared\Domain\Identity\PublicId;

/**
 * Define the boundary used to verify one organization's audit chain.
 */
interface AuditChainVerifier
{
    /**
     * Verify sequence, linkage, signatures, and the persisted chain head.
     */
    public function verify(PublicId $organizationId): AuditChainVerification;
}
