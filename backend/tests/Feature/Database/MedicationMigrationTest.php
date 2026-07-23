<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Support\Database\ReversibleMigration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class MedicationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_medication_schema_migrates_down_and_up_cleanly(): void
    {
        $migration = $this->medicationMigration();

        self::assertTrue(Schema::hasTable('medications'));
        self::assertTrue(Schema::hasTable('medication_renewal_requests'));
        self::assertTrue(Schema::hasTable('medication_idempotency_requests'));

        $migration->down();
        self::assertFalse(Schema::hasTable('medications'));
        self::assertFalse(Schema::hasTable('medication_renewal_requests'));
        self::assertFalse(Schema::hasTable('medication_idempotency_requests'));

        $migration->up();
        self::assertTrue(Schema::hasTable('medications'));
        self::assertTrue(Schema::hasTable('medication_renewal_requests'));
        self::assertTrue(Schema::hasTable('medication_idempotency_requests'));
    }

    private function medicationMigration(): ReversibleMigration
    {
        $migration = require database_path(
            'migrations/2026_07_23_000300_create_medication_tables.php',
        );

        if (! $migration instanceof ReversibleMigration) {
            throw new LogicException('The medication migration must be reversible.');
        }

        return $migration;
    }
}
