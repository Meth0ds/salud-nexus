<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application;

use App\Modules\Documents\Infrastructure\Persistence\DocumentDownloadGrant;

/**
 * Return a persisted grant together with its single-use plaintext token.
 */
final readonly class DocumentDownloadAuthorization
{
    public function __construct(
        public DocumentDownloadGrant $grant,
        public string $token,
    ) {}
}
