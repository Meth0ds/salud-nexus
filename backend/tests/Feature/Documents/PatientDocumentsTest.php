<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Modules\Documents\Domain\DocumentCategory;
use App\Modules\Documents\Domain\DocumentStatus;
use App\Modules\Documents\Infrastructure\Persistence\ClinicalDocument;
use App\Modules\Documents\Infrastructure\Persistence\DocumentDownload;
use App\Modules\Documents\Infrastructure\Persistence\DocumentDownloadGrant;
use App\Modules\Documents\Infrastructure\Persistence\DocumentPublication;
use App\Modules\Documents\Infrastructure\Persistence\DocumentVersion;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Organizations\Infrastructure\Persistence\Center;
use App\Modules\Organizations\Infrastructure\Persistence\Organization;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Patients\Infrastructure\Persistence\PatientPortalLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PatientDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_endpoints_require_a_session_authenticated_identity(): void
    {
        $this->getJson('/api/v1/patient/documents')->assertUnauthorized();
        $this->postJson('/api/v1/patient/documents/'.Str::uuid7().'/download-authorizations', [])
            ->assertUnauthorized();
        $this->get('/api/v1/patient/document-downloads/'.str_repeat('a', 43))
            ->assertUnauthorized();
    }

    public function test_list_is_patient_scoped_published_only_and_minimized(): void
    {
        Storage::fake('documents');
        $mine = $this->portalContext();
        $other = $this->portalContext();
        $published = $this->publishedDocumentFor($mine, title: 'Resumen sintético');
        $this->publishedDocumentFor($other, title: 'Documento ajeno');
        $this->unpublishedDocumentFor($mine, title: 'Borrador interno');
        $withdrawn = $this->publishedDocumentFor($mine, title: 'Documento retirado');
        $withdrawn['publication']->forceFill(['withdrawn_at' => now()])->save();

        $response = $this->actingAs($mine['account'], 'web')
            ->getJson('/api/v1/patient/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published['document']->public_id)
            ->assertJsonPath('data.0.title', 'Resumen sintético')
            ->assertJsonPath('data.0.category', 'care_summary')
            ->assertJsonPath('data.0.center.name', $mine['center']->name)
            ->assertJsonPath('data.0.file.mime_type', 'application/pdf')
            ->assertJsonPath('data.0.file.version', 1)
            ->assertJsonPath('data.0.integrity_status', 'verified')
            ->assertJsonPath('data.0.can_download', true)
            ->assertJsonMissing(['title' => 'Documento ajeno'])
            ->assertJsonMissing(['title' => 'Borrador interno'])
            ->assertJsonMissing(['title' => 'Documento retirado'])
            ->assertJsonMissingPath('data.0.patient_id')
            ->assertJsonMissingPath('data.0.organization_id')
            ->assertJsonMissingPath('data.0.file.sha256')
            ->assertJsonMissingPath('data.0.file.storage_path');

        self::assertSame(['data', 'meta'], array_keys($response->json()));
        $this->assertDatabaseHas('audit_events', [
            'organization_public_id' => $mine['organization']->public_id,
            'action' => 'patient.documents.listed',
            'result' => 'succeeded',
        ]);
    }

    public function test_download_authorization_is_short_lived_hashed_and_contains_no_phi(): void
    {
        Storage::fake('documents');
        Carbon::setTestNow('2026-07-23 10:00:00');
        $context = $this->portalContext();
        $published = $this->publishedDocumentFor($context, title: 'Informe confidencial');

        try {
            $response = $this->actingAs($context['account'], 'web')
                ->postJson(
                    '/api/v1/patient/documents/'.$published['document']->public_id.'/download-authorizations',
                    [],
                )
                ->assertCreated()
                ->assertJsonPath('data.document_id', $published['document']->public_id)
                ->assertJsonPath('data.expires_at', '2026-07-23T10:01:30+00:00');

            $downloadUrl = $response->json('data.download_url');
            self::assertIsString($downloadUrl);
            self::assertMatchesRegularExpression(
                '#^/api/v1/patient/document-downloads/[A-Za-z0-9_-]{43}$#',
                $downloadUrl,
            );
            self::assertStringNotContainsString('Informe', $downloadUrl);
            self::assertStringNotContainsString($context['patient']->public_id, $downloadUrl);

            $rawToken = basename($downloadUrl);
            $grant = DocumentDownloadGrant::query()->sole();
            self::assertNotSame($rawToken, $grant->token_hash);
            self::assertSame(hash('sha256', $rawToken), $grant->token_hash);
            self::assertNull($grant->consumed_at);
            $this->assertDatabaseHas('audit_events', [
                'action' => 'patient.document.download_authorized',
                'target_public_id' => $published['document']->public_id,
                'result' => 'succeeded',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_download_reauthorizes_verifies_integrity_and_is_single_use(): void
    {
        Storage::fake('documents');
        $context = $this->portalContext();
        $content = "%PDF-1.7\nSynthetic clinical document\n%%EOF";
        $published = $this->publishedDocumentFor($context, content: $content);

        $authorization = $this->actingAs($context['account'], 'web')
            ->postJson(
                '/api/v1/patient/documents/'.$published['document']->public_id.'/download-authorizations',
                [],
            )
            ->assertCreated();
        $downloadUrl = $authorization->json('data.download_url');
        self::assertIsString($downloadUrl);

        $this->get($downloadUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Content-Disposition')
            ->assertContent($content);

        $this->assertDatabaseHas('document_download_grants', ['consumed_at' => now()]);
        self::assertSame(1, DocumentDownload::query()->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'patient.document.downloaded',
            'target_public_id' => $published['document']->public_id,
            'result' => 'succeeded',
        ]);

        $this->get($downloadUrl)
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
        self::assertSame(1, DocumentDownload::query()->count());
    }

    public function test_foreign_withdrawn_expired_and_tampered_documents_fail_closed(): void
    {
        Storage::fake('documents');
        $mine = $this->portalContext();
        $other = $this->portalContext();
        $foreign = $this->publishedDocumentFor($other);

        $this->actingAs($mine['account'], 'web')
            ->postJson(
                '/api/v1/patient/documents/'.$foreign['document']->public_id.'/download-authorizations',
                [],
            )
            ->assertNotFound();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'patient.document.download_authorization_denied',
            'target_public_id' => $foreign['document']->public_id,
            'result' => 'denied',
        ]);

        $own = $this->publishedDocumentFor($mine);
        $authorization = $this->postJson(
            '/api/v1/patient/documents/'.$own['document']->public_id.'/download-authorizations',
            [],
        )->assertCreated();
        $downloadUrl = $authorization->json('data.download_url');
        self::assertIsString($downloadUrl);

        DocumentDownloadGrant::query()->update(['expires_at' => now()->subSecond()]);
        $this->get($downloadUrl)->assertNotFound();

        $authorization = $this->postJson(
            '/api/v1/patient/documents/'.$own['document']->public_id.'/download-authorizations',
            [],
        )->assertCreated();
        $downloadUrl = $authorization->json('data.download_url');
        self::assertIsString($downloadUrl);
        Storage::disk('documents')->put($own['version']->storage_path, 'tampered bytes');

        $this->get($downloadUrl)
            ->assertInternalServerError()
            ->assertHeader('Content-Type', 'application/problem+json');
        self::assertSame(0, DocumentDownload::query()->count());
        self::assertNull(DocumentDownloadGrant::query()->latest('id')->firstOrFail()->consumed_at);

        $own['publication']->forceFill(['withdrawn_at' => now()])->save();
        $this->postJson(
            '/api/v1/patient/documents/'.$own['document']->public_id.'/download-authorizations',
            [],
        )->assertNotFound();
    }

    public function test_document_versions_are_immutable_after_issue(): void
    {
        Storage::fake('documents');
        $published = $this->publishedDocumentFor($this->portalContext());

        $this->expectException(\LogicException::class);
        $published['version']->forceFill(['byte_size' => 1])->save();
    }

    public function test_download_authorization_requires_csrf_outside_testing_environment(): void
    {
        Storage::fake('documents');
        $context = $this->portalContext();
        $published = $this->publishedDocumentFor($context);
        $this->app['env'] = 'local';

        $this->actingAs($context['account'], 'web')
            ->withSession(['_token' => 'expected-csrf-token'])
            ->postJson(
                '/api/v1/patient/documents/'.$published['document']->public_id.'/download-authorizations',
                [],
            )
            ->assertStatus(419)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    /**
     * Build an authenticated patient portal fixture for document scenarios.
     *
     * @return array{account: IdentityAccount, organization: Organization, center: Center, patient: Patient}
     */
    private function portalContext(): array
    {
        $account = IdentityAccount::factory()->create();
        $organization = Organization::factory()->create();
        $center = Center::factory()->create(['organization_id' => $organization->id]);
        $patient = Patient::factory()->create([
            'organization_id' => $organization->id,
            'home_center_id' => $center->id,
        ]);
        PatientPortalLink::factory()->create([
            'organization_id' => $organization->id,
            'patient_id' => $patient->id,
            'identity_account_id' => $account->id,
        ]);

        return compact('account', 'organization', 'center', 'patient');
    }

    /**
     * Create and publish a stored clinical document for the patient.
     *
     * @param  array{account: IdentityAccount, organization: Organization, center: Center, patient: Patient}  $context
     * @return array{document: ClinicalDocument, version: DocumentVersion, publication: DocumentPublication}
     */
    private function publishedDocumentFor(
        array $context,
        string $title = 'Resumen de atención',
        string $content = "%PDF-1.7\nSynthetic document\n%%EOF",
    ): array {
        $document = ClinicalDocument::factory()->create([
            'organization_id' => $context['organization']->id,
            'patient_id' => $context['patient']->id,
            'center_id' => $context['center']->id,
            'title' => $title,
            'category' => DocumentCategory::CareSummary,
            'status' => DocumentStatus::Issued,
        ]);
        $versionPublicId = Str::uuid7()->toString();
        $path = 'objects/'.$document->public_id.'/'.$versionPublicId.'.pdf';
        Storage::disk('documents')->put($path, $content);
        $version = DocumentVersion::query()->create([
            'organization_id' => $context['organization']->id,
            'document_id' => $document->id,
            'public_id' => $versionPublicId,
            'version_number' => 1,
            'mime_type' => 'application/pdf',
            'byte_size' => strlen($content),
            'sha256' => hash('sha256', $content),
            'storage_disk' => 'documents',
            'storage_path' => $path,
            'issued_at' => now(),
        ]);
        $publication = DocumentPublication::query()->create([
            'organization_id' => $context['organization']->id,
            'patient_id' => $context['patient']->id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'public_id' => Str::uuid7()->toString(),
            'published_at' => now(),
        ]);

        return compact('document', 'version', 'publication');
    }

    /**
     * Create a draft clinical document that is not visible to the patient.
     *
     * @param  array{account: IdentityAccount, organization: Organization, center: Center, patient: Patient}  $context
     */
    private function unpublishedDocumentFor(array $context, string $title): ClinicalDocument
    {
        return ClinicalDocument::factory()->create([
            'organization_id' => $context['organization']->id,
            'patient_id' => $context['patient']->id,
            'center_id' => $context['center']->id,
            'title' => $title,
            'status' => DocumentStatus::Draft,
        ]);
    }
}
