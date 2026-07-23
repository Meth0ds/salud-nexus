<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application;

use App\Modules\Documents\Domain\DocumentStatus;
use App\Modules\Documents\Infrastructure\Persistence\ClinicalDocument;
use App\Modules\Documents\Infrastructure\Persistence\DocumentDownload;
use App\Modules\Documents\Infrastructure\Persistence\DocumentDownloadGrant;
use App\Modules\Documents\Infrastructure\Persistence\DocumentVersion;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use Illuminate\Support\Facades\DB;

/**
 * Consume a one-time download grant and record successful document delivery.
 */
final readonly class ConsumePatientDocumentDownload
{
    public function __construct(private VerifyDocumentFile $verifyFile) {}

    /**
     * Verify ownership, publication, token expiry, and file integrity atomically.
     */
    public function handle(
        IdentityAccount $identity,
        Patient $patient,
        string $token,
        string $requestPublicId,
    ): AuthorizedDocumentDownload {
        return DB::transaction(function () use ($identity, $patient, $token, $requestPublicId): AuthorizedDocumentDownload {
            $grant = DocumentDownloadGrant::query()
                ->where('organization_id', $patient->organization_id)
                ->where('patient_id', $patient->id)
                ->where('identity_account_id', $identity->id)
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();
            $document = ClinicalDocument::query()
                ->whereKey($grant->document_id)
                ->where('organization_id', $patient->organization_id)
                ->where('patient_id', $patient->id)
                ->where('status', DocumentStatus::Issued->value)
                ->whereHas('activePublication', static function ($query) use ($grant): void {
                    $query->where('document_version_id', $grant->document_version_id);
                })
                ->firstOrFail();
            $version = DocumentVersion::query()
                ->whereKey($grant->document_version_id)
                ->where('organization_id', $patient->organization_id)
                ->where('document_id', $document->id)
                ->firstOrFail();
            $contents = $this->verifyFile->handle($version);

            $grant->forceFill(['consumed_at' => now()])->save();
            DocumentDownload::query()->create([
                'organization_id' => $patient->organization_id,
                'identity_account_id' => $identity->id,
                'patient_id' => $patient->id,
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'document_download_grant_id' => $grant->id,
                'request_public_id' => $requestPublicId,
                'outcome' => 'succeeded',
                'downloaded_at' => now(),
            ]);

            return new AuthorizedDocumentDownload($document, $version, $grant, $contents);
        }, attempts: 3);
    }
}
