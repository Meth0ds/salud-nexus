<?php

declare(strict_types=1);

use App\Support\Database\ReversibleMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration implements ReversibleMigration
{
    /**
     * Run the tamper-evident audit chain migrations.
     */
    public function up(): void
    {
        Schema::create('audit_chain_heads', function (Blueprint $table): void {
            $table->uuid('organization_public_id')->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->char('last_hash', 64)->nullable();
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedTinyInteger('hash_version')->default(1);
            $table->uuid('organization_public_id');
            $table->unsignedBigInteger('chain_sequence');
            $table->uuid('actor_public_id');
            $table->uuid('identity_public_id');
            $table->uuid('session_public_id');
            $table->uuid('center_public_id')->nullable();
            $table->string('actor_role', 48);
            $table->string('purpose', 48);
            $table->unsignedTinyInteger('authentication_level');
            $table->string('action', 120);
            $table->string('target_type', 80);
            $table->uuid('target_public_id')->nullable();
            $table->string('result', 16);
            $table->uuid('request_id');
            $table->char('occurred_at', 27);
            $table->text('metadata_json');
            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64);

            $table->unique(['organization_public_id', 'chain_sequence']);
            $table->index(['organization_public_id', 'occurred_at']);
            $table->index(['organization_public_id', 'actor_public_id', 'occurred_at']);
            $table->index(['organization_public_id', 'target_type', 'target_public_id']);
            $table->index('request_id');
        });
    }

    /**
     * Reverse the tamper-evident audit chain migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('audit_chain_heads');
    }
};
