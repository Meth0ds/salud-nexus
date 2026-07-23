<?php

declare(strict_types=1);

use App\Support\Database\ReversibleMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration implements ReversibleMigration
{
    /**
     * Run the patient booking migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 32)->unique();
            $table->string('name', 160);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });

        Schema::create('centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->string('timezone', 64);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->unique(['id', 'organization_id']);
            $table->unique('organization_id', 'centers_single_per_organization_unique');
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('home_center_id')->nullable();
            $table->uuid('public_id')->unique();
            $table->string('record_number', 64);
            $table->string('display_name', 160);
            $table->date('date_of_birth');
            $table->enum('status', ['active', 'inactive', 'deceased'])->default('active')->index();
            $table->timestamps();

            $table->unique(['id', 'organization_id']);
            $table->unique(['organization_id', 'record_number']);
            $table->index(['organization_id', 'status']);
            $table->foreign(['home_center_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('centers')
                ->restrictOnDelete();
        });

        Schema::create('patient_portal_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->foreignId('identity_account_id')
                ->constrained('identity_accounts')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique('identity_account_id');
            $table->unique('patient_id');
            $table->index(['organization_id', 'identity_account_id']);
            $table->foreign(['patient_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('patients')
                ->restrictOnDelete();
        });

        Schema::create('health_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['id', 'organization_id']);
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('appointment_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('health_service_id');
            $table->uuid('public_id')->unique();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->unsignedSmallInteger('duration_minutes');
            $table->enum('attendance_mode', ['in_person', 'video', 'phone']);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['id', 'organization_id']);
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
            $table->foreign(['health_service_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('health_services')
                ->restrictOnDelete();
        });

        Schema::create('appointment_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('center_id');
            $table->unsignedBigInteger('appointment_type_id');
            $table->uuid('public_id')->unique();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->enum('status', ['open', 'blocked'])->default('open')->index();
            $table->string('location_label', 160)->nullable();
            $table->string('professional_display_name', 160)->nullable();
            $table->timestamps();

            $table->unique(['id', 'center_id', 'appointment_type_id', 'organization_id']);
            $table->unique(['center_id', 'starts_at']);
            $table->index(['organization_id', 'status', 'starts_at']);
            $table->index(['organization_id', 'appointment_type_id', 'starts_at']);
            $table->foreign(['center_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('centers')
                ->restrictOnDelete();
            $table->foreign(['appointment_type_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('appointment_types')
                ->restrictOnDelete();
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('center_id');
            $table->unsignedBigInteger('appointment_type_id');
            $table->unsignedBigInteger('slot_id');
            $table->uuid('public_id')->unique();
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'no_show'])
                ->default('scheduled')
                ->index();
            $table->enum('attendance_mode', ['in_person', 'video', 'phone']);
            $table->string('center_timezone', 64);
            $table->string('location_label', 160)->nullable();
            $table->string('professional_display_name', 160)->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestamps();

            $table->unique('slot_id');
            $table->index(['organization_id', 'patient_id', 'starts_at']);
            $table->index(['organization_id', 'patient_id', 'status', 'starts_at']);
            $table->foreign(['patient_id', 'organization_id'])
                ->references(['id', 'organization_id'])
                ->on('patients')
                ->restrictOnDelete();
            $table->foreign([
                'slot_id',
                'center_id',
                'appointment_type_id',
                'organization_id',
            ])->references([
                'id',
                'center_id',
                'appointment_type_id',
                'organization_id',
            ])->on('appointment_slots')->restrictOnDelete();
        });

        Schema::create('idempotency_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('identity_account_id')
                ->constrained('identity_accounts')
                ->restrictOnDelete();
            $table->string('route', 128);
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->enum('status', ['processing', 'completed'])->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->uuid('resource_public_id')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestamps();

            $table->unique(
                ['identity_account_id', 'route', 'idempotency_key'],
                'idempotency_actor_route_key_unique',
            );
            $table->index(['expires_at', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appointment_slots ADD CONSTRAINT appointment_slots_time_order_check CHECK (ends_at > starts_at)',
            );
            DB::statement(
                'ALTER TABLE appointments ADD CONSTRAINT appointments_time_order_check CHECK (ends_at > starts_at)',
            );
            DB::statement(
                'ALTER TABLE appointment_types ADD CONSTRAINT appointment_types_duration_check CHECK (duration_minutes BETWEEN 5 AND 480)',
            );
        }
    }

    /**
     * Reverse the patient booking migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_requests');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_slots');
        Schema::dropIfExists('appointment_types');
        Schema::dropIfExists('health_services');
        Schema::dropIfExists('patient_portal_links');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('centers');
        Schema::dropIfExists('organizations');
    }
};
