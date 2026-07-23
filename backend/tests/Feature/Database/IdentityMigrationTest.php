<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Support\Database\ReversibleMigration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class IdentityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_and_session_schema_migrates_down_and_up_cleanly(): void
    {
        $migration = $this->identityMigration();

        $migration->down();

        self::assertFalse(Schema::hasTable('sessions'));
        self::assertFalse(Schema::hasTable('identity_password_reset_tokens'));
        self::assertFalse(Schema::hasTable('identity_accounts'));

        $migration->up();

        self::assertTrue(Schema::hasTable('identity_accounts'));
        self::assertTrue(Schema::hasColumns('identity_accounts', [
            'public_id',
            'display_name',
            'email',
            'status',
            'password',
        ]));
        self::assertTrue(Schema::hasTable('identity_password_reset_tokens'));
        self::assertTrue(Schema::hasTable('sessions'));
    }

    private function identityMigration(): ReversibleMigration
    {
        $migration = require database_path('migrations/0001_01_01_000000_create_users_table.php');

        if (! $migration instanceof ReversibleMigration) {
            throw new LogicException('The identity migration must be reversible.');
        }

        return $migration;
    }
}
