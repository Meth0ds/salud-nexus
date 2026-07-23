<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application;

use App\Modules\Documents\Infrastructure\Persistence\DocumentDownloadGrant;
use App\Modules\Documents\Infrastructure\Persistence\DocumentPublication;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issue a short-lived, one-time authorization for a verified patient document.
 */
final readonly class CreatePatientDocumentDownloadAuthorization
{
    public function __construct(
        private FindPublishedPatientDocument $findDocument,
        private VerifyDocumentFile $verifyFile,
    ) {}

    /**
     * Create and persist an opaque download grant after all integrity checks pass.
     */
    public function handle(
        IdentityAccount $identity,
        Patient $patient,
        string $documentPublicId,
    ): DocumentDownloadAuthorization {
        $document = $this->findDocument->handle($patient, $documentPublicId);
        $publication = $document->activePublication;
        if (! $publication instanceof DocumentPublication) {
            throw new RuntimeException('The document is not currently published.');
        }
        $version = $publication->version;
        $this->verifyFile->handle($version);

        $ttlSeconds = config('documents.download_grant_ttl_seconds');
        if (! is_int($ttlSeconds) || $ttlSeconds < 30 || $ttlSeconds > 300) {
            throw new RuntimeException('The document authorization lifetime is invalid.');
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $grant = DB::transaction(static fn (): DocumentDownloadGrant => DocumentDownloadGrant::query()->create([
            'organization_id' => $patient->organization_id,
            'identity_account_id' => $identity->id,
            'patient_id' => $patient->id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addSeconds($ttlSeconds),
            'consumed_at' => null,
        ]));

        return new DocumentDownloadAuthorization($grant, $token);
    }
}
