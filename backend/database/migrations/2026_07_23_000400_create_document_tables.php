<?php

declare(strict_types=1);

use App\Support\Database\ReversibleMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration implements ReversibleMigration
{
    /**
     * Run the protected clinical document migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('center_id');
            $table->uuid('public_id')->unique();
            $table->string('title', 160);
            $table->enum('category', [
                'attendance_certificate',
                'care_summary',
                'consent',
                'laboratory',
                'medication_summary',
            ]);
            $table->enum('status', ['draft', 'issued', 'withdrawn'])->default('draft');
            $table->timestampTz('retention_until')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'organization_id']);
            $table->index(['organization_id', 'patient_id', 'status']);
            $table->foreign(['patient_id', 'organization_id'], 'documents_patient_organization_fk')
                ->references(['id', 'organization_id'])
                ->on('patients')
                ->restrictOnDelete();
            $table->foreign(['center_id', 'organization_id'], 'documents_center_organization_fk')
                ->references(['id', 'organization_id'])
                ->on('centers')
                ->restrictOnDelete();
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('document_id');
            $table->uuid('public_id')->unique();
            $table->unsignedSmallInteger('version_number');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('storage_disk', 32);
            $table->string('storage_path', 255);
            $table->timestampTz('issued_at');
            $table->timestampsTz();

            $table->unique(['id', 'organization_id']);
            $table->unique(['id', 'document_id', 'organization_id'], 'document_versions_identity_unique');
            $table->unique(['document_id', 'version_number']);
            $table->unique(['storage_disk', 'storage_path']);
            $table->foreign(['document_id', 'organization_id'], 'document_versions_document_organization_fk')
                ->references(['id', 'organization_id'])
                ->on('documents')
                ->restrictOnDelete();
        });

        Schema::create('document_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_version_id');
            $table->uuid('public_id')->unique();
            $table->timestampTz('published_at');
            $table->timestampTz('withdrawn_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'organization_id']);
            $table->unique(['document_id', 'document_version_id']);
            $table->index(['organization_id', 'patient_id', 'withdrawn_at', 'published_at'], 'document_publications_patient_active_idx');
            $table->foreign(['patient_id', 'organization_id'], 'document_publications_patient_organization_fk')
                ->references(['id', 'organization_id'])
                ->on('patients')
                ->restrictOnDelete();
            $table->foreign(
                ['document_version_id', 'document_id', 'organization_id'],
                'document_publications_version_document_organization_fk',
            )
                ->references(['id', 'document_id', 'organization_id'])
                ->on('document_versions')
                ->restrictOnDelete();
        });

        Schema::create('document_download_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('identity_account_id')->constrained('identity_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_version_id');
            $table->uuid('public_id')->unique();
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'organization_id']);
            $table->index(['identity_account_id', 'expires_at', 'consumed_at'], 'document_download_grants_active_idx');
            $table->foreign(['patient_id', 'organization_id'], 'document_download_grants_patient_organization_fk')
                ->references(['id', 'organization_id'])
                ->on('patients')
                ->restrictOnDelete();
            $table->foreign(
                ['document_version_id', 'document_id', 'organization_id'],
                'document_download_grants_version_document_organization_fk',
            )
                ->references(['id', 'document_id', 'organization_id'])
                ->on('document_versions')
                ->restrictOnDelete();
        });

        Schema::create('document_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('identity_account_id')->constrained('identity_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('document_download_grant_id');
            $table->uuid('public_id')->unique();
            $table->uuid('request_public_id');
            $table->enum('outcome', ['succeeded', 'failed']);
            $table->timestampTz('downloaded_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique('document_download_grant_id');
            $table->index(['organization_id', 'patient_id', 'downloaded_at']);
            $table->foreign(['patient_id', 'organization_id'], 'document_downloads_patient_organization_fk')
                ->references(['id', 'organization_id'])
                ->on('patients')
                ->restrictOnDelete();
            $table->foreign(
                ['document_version_id', 'document_id', 'organization_id'],
                'document_downloads_version_document_organization_fk',
            )
                ->references(['id', 'document_id', 'organization_id'])
                ->on('document_versions')
                ->restrictOnDelete();
            $table->foreign(['document_download_grant_id', 'organization_id'], 'document_downloads_grant_organization_fk')
                ->references(['id', 'organization_id'])
                ->on('document_download_grants')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the protected clinical document migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_downloads');
        Schema::dropIfExists('document_download_grants');
        Schema::dropIfExists('document_publications');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
