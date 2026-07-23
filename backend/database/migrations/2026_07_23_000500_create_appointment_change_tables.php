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
     * Separate current slot ownership from the immutable appointment record.
     *
     * An appointment may retain historical slot references after cancellation or
     * rescheduling, while the allocation table remains the only source of truth
     * for whether a slot is currently occupied.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropUnique('appointments_slot_id_unique');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->unique(
                ['id', 'organization_id'],
                'appointments_tenant_identity_unique',
            );
        });

        Schema::table('appointment_slots', function (Blueprint $table): void {
            $table->unique(
                ['id', 'organization_id'],
                'appointment_slots_tenant_identity_unique',
            );
        });

        Schema::create('appointment_slot_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('slot_id');
            $table->timestampTz('created_at')->useCurrent();

            // Global surrogate IDs make these constraints tenant-safe and race-safe.
            $table->unique('appointment_id', 'appointment_slot_allocations_appointment_unique');
            $table->unique('slot_id', 'appointment_slot_allocations_slot_unique');
            $table->foreign(
                ['appointment_id', 'organization_id'],
                'appointment_slot_allocations_appointment_org_fk',
            )
                ->references(['id', 'organization_id'])
                ->on('appointments')
                ->restrictOnDelete();
            $table->foreign(
                ['slot_id', 'organization_id'],
                'appointment_slot_allocations_slot_org_fk',
            )
                ->references(['id', 'organization_id'])
                ->on('appointment_slots')
                ->restrictOnDelete();
        });

        Schema::create('appointment_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->foreignId('identity_account_id')
                ->constrained('identity_accounts')
                ->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->enum('transition', ['cancelled', 'rescheduled']);
            $table->enum('from_status', ['scheduled', 'cancelled', 'completed', 'no_show']);
            $table->enum('to_status', ['scheduled', 'cancelled', 'completed', 'no_show']);
            $table->unsignedBigInteger('from_slot_id');
            $table->unsignedBigInteger('to_slot_id')->nullable();
            $table->string('reason_code', 48)->nullable();
            $table->unsignedInteger('from_version');
            $table->unsignedInteger('to_version');
            $table->uuid('request_public_id')->unique();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['id', 'organization_id'], 'appointment_changes_tenant_identity_unique');
            $table->index(
                ['organization_id', 'appointment_id', 'occurred_at'],
                'appointment_changes_timeline_idx',
            );
            $table->foreign(
                ['appointment_id', 'organization_id'],
                'appointment_changes_appointment_org_fk',
            )
                ->references(['id', 'organization_id'])
                ->on('appointments')
                ->restrictOnDelete();
            $table->foreign(
                ['from_slot_id', 'organization_id'],
                'appointment_changes_from_slot_org_fk',
            )
                ->references(['id', 'organization_id'])
                ->on('appointment_slots')
                ->restrictOnDelete();
            $table->foreign(
                ['to_slot_id', 'organization_id'],
                'appointment_changes_to_slot_org_fk',
            )
                ->references(['id', 'organization_id'])
                ->on('appointment_slots')
                ->restrictOnDelete();
        });

        // Existing scheduled appointments must own their slots before new writes begin.
        DB::table('appointment_slot_allocations')->insertUsing(
            ['organization_id', 'appointment_id', 'slot_id', 'created_at'],
            DB::table('appointments')
                ->select('organization_id')
                ->selectRaw('id AS appointment_id, slot_id, CURRENT_TIMESTAMP AS created_at')
                ->where('status', 'scheduled'),
        );

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE appointment_changes
                ADD CONSTRAINT appointment_changes_version_sequence_check
                CHECK (from_version > 0 AND to_version = from_version + 1)
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE appointment_changes
                ADD CONSTRAINT appointment_changes_transition_payload_check
                CHECK (
                    (
                        transition = 'cancelled'
                        AND from_status = 'scheduled'
                        AND to_status = 'cancelled'
                        AND to_slot_id IS NULL
                        AND reason_code IS NOT NULL
                    )
                    OR
                    (
                        transition = 'rescheduled'
                        AND from_status = 'scheduled'
                        AND to_status = 'scheduled'
                        AND to_slot_id IS NOT NULL
                        AND reason_code IS NULL
                    )
                )
                SQL);
        }
    }

    /**
     * Remove change tracking and restore the pre-change booking constraint.
     *
     * Rollback intentionally fails if production data already reuses a historical
     * slot; silently discarding appointment history would be unsafe.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_changes');
        Schema::dropIfExists('appointment_slot_allocations');

        Schema::table('appointment_slots', function (Blueprint $table): void {
            $table->dropUnique('appointment_slots_tenant_identity_unique');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropUnique('appointments_tenant_identity_unique');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('version');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->unique('slot_id');
        });
    }
};
