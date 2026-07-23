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
     * Create encrypted MFA methods, one-use recovery codes, and security events.
     */
    public function up(): void
    {
        Schema::create('identity_mfa_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('identity_account_id')
                ->constrained('identity_accounts')
                ->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('type', 32);
            $table->string('status', 16)->default('pending');
            $table->text('secret');
            $table->unsignedBigInteger('last_used_step')->nullable();
            $table->timestampTz('enrollment_expires_at')->nullable();
            $table->timestampTz('secret_revealed_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['identity_account_id', 'type'],
                'identity_mfa_methods_account_type_unique',
            );
            $table->index(
                ['status', 'enrollment_expires_at'],
                'identity_mfa_methods_enrollment_expiry_idx',
            );
        });

        Schema::create('identity_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('identity_mfa_method_id')
                ->constrained('identity_mfa_methods')
                ->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->char('lookup_digest', 64)->unique();
            $table->string('code_hash');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(
                ['identity_mfa_method_id', 'used_at'],
                'identity_recovery_codes_available_idx',
            );
        });

        Schema::create('identity_security_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('identity_account_id')
                ->nullable()
                ->constrained('identity_accounts')
                ->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->uuid('request_public_id');
            $table->string('event_type', 80);
            $table->string('result', 16);
            $table->unsignedTinyInteger('authentication_level')->default(0);
            $table->text('metadata_json')->default('{}');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(
                ['identity_account_id', 'occurred_at'],
                'identity_security_events_account_timeline_idx',
            );
            $table->index(
                ['event_type', 'result', 'occurred_at'],
                'identity_security_events_detection_idx',
            );
            $table->index('request_public_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE identity_mfa_methods
                ADD CONSTRAINT identity_mfa_methods_type_check
                CHECK (type IN ('totp'))
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE identity_mfa_methods
                ADD CONSTRAINT identity_mfa_methods_status_check
                CHECK (status IN ('pending', 'active', 'disabled'))
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE identity_security_events
                ADD CONSTRAINT identity_security_events_result_check
                CHECK (result IN ('succeeded', 'failed', 'denied'))
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE identity_security_events
                ADD CONSTRAINT identity_security_events_aal_check
                CHECK (authentication_level BETWEEN 0 AND 2)
                SQL);
        }
    }

    /**
     * Remove the MFA persistence boundary in dependency-safe order.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_security_events');
        Schema::dropIfExists('identity_recovery_codes');
        Schema::dropIfExists('identity_mfa_methods');
    }
};
