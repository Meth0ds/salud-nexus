<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Support\Database\ReversibleMigration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class AuditMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_schema_migrates_down_and_up_cleanly(): void
    {
        $migration = $this->auditMigration();

        self::assertTrue(Schema::hasTable('audit_events'));
        self::assertTrue(Schema::hasTable('audit_chain_heads'));

        $migration->down();
        self::assertFalse(Schema::hasTable('audit_events'));
        self::assertFalse(Schema::hasTable('audit_chain_heads'));

        $migration->up();
        self::assertTrue(Schema::hasTable('audit_events'));
        self::assertTrue(Schema::hasTable('audit_chain_heads'));
    }

    private function auditMigration(): ReversibleMigration
    {
        $migration = require database_path(
            'migrations/2026_07_23_000200_create_audit_chain_tables.php',
        );

        if (! $migration instanceof ReversibleMigration) {
            throw new LogicException('The audit migration must be reversible.');
        }

        return $migration;
    }
}
