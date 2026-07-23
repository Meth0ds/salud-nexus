<?php

declare(strict_types=1);

use App\Support\Database\ReversibleMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration implements ReversibleMigration
{
    /**
     * Run the patient medication migrations.
     */
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->uuid('public_id')->unique();
            $table->enum('source', ['professional_record', 'patient_declaration']);
            $table->string('name', 160);
            $table->string('presentation', 120)->nullable();
            $table->string('schedule_label', 160);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->uuid('recorded_by_identity_public_id')->nullable();
            $table->timestamps();

            $table->unique(['id', 'organization_id']);
            $table->index(['organization_id', 'patient_id', 'status']);
            $table->foreign(['patient_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('patients')
                ->restrictOnDelete();
        });

        Schema::create('medication_renewal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('medication_id');
            $table->uuid('public_id')->unique();
            $table->enum('status', ['submitted', 'accepted', 'rejected', 'cancelled'])
                ->default('submitted');
            $table->timestampTz('requested_at');
            $table->timestamps();

            $table->unique(['id', 'organization_id']);
            $table->index(['organization_id', 'patient_id', 'status']);
            $table->index(['organization_id', 'medication_id', 'status']);
            $table->foreign(['patient_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('patients')
                ->restrictOnDelete();
            $table->foreign(['medication_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('medications')
                ->restrictOnDelete();
        });

        Schema::create('medication_idempotency_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('identity_account_id')
                ->constrained('identity_accounts')
                ->restrictOnDelete();
            $table->string('operation', 80);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->enum('status', ['processing', 'completed']);
            $table->string('resource_type', 48)->nullable();
            $table->uuid('resource_public_id')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestamps();

            $table->unique(['identity_account_id', 'operation', 'idempotency_key_hash'], 'medication_idempotency_unique');
            $table->index(['expires_at', 'status']);
        });
    }

    /**
     * Reverse the patient medication migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_idempotency_requests');
        Schema::dropIfExists('medication_renewal_requests');
        Schema::dropIfExists('medications');
    }
};
