<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application;

use App\Modules\Documents\Infrastructure\Persistence\ClinicalDocument;
use App\Modules\Documents\Infrastructure\Persistence\DocumentDownloadGrant;
use App\Modules\Documents\Infrastructure\Persistence\DocumentVersion;

/**
 * Carry a verified document and its consumed authorization to the HTTP edge.
 */
final readonly class AuthorizedDocumentDownload
{
    public function __construct(
        public ClinicalDocument $document,
        public DocumentVersion $version,
        public DocumentDownloadGrant $grant,
        public string $contents,
    ) {}

    /**
     * Build the safe attachment name exposed to the patient.
     */
    public function fileName(): string
    {
        return 'documento-'.$this->document->public_id.'.pdf';
    }
}
