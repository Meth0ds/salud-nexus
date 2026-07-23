<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use App\Support\Database\ReversibleMigration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * Verify the reversible and secret-safe persistence boundary for MFA.
 */
final class MfaMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * List the tables owned by the MFA persistence migration.
     *
     * @var list<string>
     */
    private const TABLES = [
        'identity_mfa_methods',
        'identity_recovery_codes',
        'identity_security_events',
    ];

    public function test_mfa_schema_migrates_down_and_up_cleanly(): void
    {
        $migration = $this->mfaMigration();

        $migration->down();

        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table));
        }

        $migration->up();

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table));
        }

        self::assertTrue(Schema::hasColumns('identity_mfa_methods', [
            'identity_account_id',
            'public_id',
            'type',
            'status',
            'secret',
            'last_used_step',
            'enrollment_expires_at',
            'secret_revealed_at',
            'confirmed_at',
            'disabled_at',
        ]));
        self::assertTrue(Schema::hasColumns('identity_recovery_codes', [
            'identity_mfa_method_id',
            'public_id',
            'lookup_digest',
            'code_hash',
            'used_at',
        ]));
        self::assertTrue(Schema::hasColumns('identity_security_events', [
            'identity_account_id',
            'public_id',
            'request_public_id',
            'event_type',
            'result',
            'authentication_level',
            'metadata_json',
            'occurred_at',
        ]));
    }

    public function test_an_account_cannot_have_two_totp_methods(): void
    {
        $account = IdentityAccount::factory()->create();

        $this->createMethod($account);

        $this->expectException(QueryException::class);

        $this->createMethod($account);
    }

    public function test_totp_secrets_are_encrypted_and_hidden_by_the_model(): void
    {
        $account = IdentityAccount::factory()->create();
        $plainSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $method = $this->createMethod($account, $plainSecret);
        $storedSecret = DB::table('identity_mfa_methods')
            ->where('id', $method->id)
            ->value('secret');

        self::assertIsString($storedSecret);
        self::assertNotSame($plainSecret, $storedSecret);
        self::assertStringNotContainsString($plainSecret, $storedSecret);
        self::assertSame($plainSecret, $method->fresh()?->secret);
        self::assertArrayNotHasKey('secret', $method->toArray());
        self::assertArrayNotHasKey('last_used_step', $method->toArray());
    }

    /**
     * Create a pending TOTP method without using mass assignment.
     */
    private function createMethod(
        IdentityAccount $account,
        string $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
    ): IdentityMfaMethod {
        $method = new IdentityMfaMethod;
        $method->identity_account_id = $account->id;
        $method->type = MfaMethodType::Totp;
        $method->status = MfaMethodStatus::Pending;
        $method->secret = $secret;
        $method->enrollment_expires_at = now()->toImmutable()->addMinutes(5);
        $method->save();

        return $method;
    }

    /**
     * Resolve the MFA migration under test.
     */
    private function mfaMigration(): ReversibleMigration
    {
        $migration = require database_path('migrations/2026_07_23_000600_create_identity_mfa_tables.php');

        if (! $migration instanceof ReversibleMigration) {
            throw new LogicException('The MFA migration must be reversible.');
        }

        return $migration;
    }
}
