<?php

declare(strict_types=1);

use App\Support\Database\ReversibleMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration implements ReversibleMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('identity_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('display_name');
            $table->string('email', 254)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('identity_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('identity_accounts')
                ->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('identity_password_reset_tokens');
        Schema::dropIfExists('identity_accounts');
    }
};
