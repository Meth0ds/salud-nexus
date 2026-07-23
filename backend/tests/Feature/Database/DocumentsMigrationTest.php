<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Support\Database\ReversibleMigration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class DocumentsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_schema_migrates_down_and_up_cleanly(): void
    {
        $migration = $this->documentsMigration();
        $tables = [
            'documents',
            'document_versions',
            'document_publications',
            'document_download_grants',
            'document_downloads',
        ];

        foreach ($tables as $table) {
            self::assertTrue(Schema::hasTable($table));
        }

        $migration->down();
        foreach ($tables as $table) {
            self::assertFalse(Schema::hasTable($table));
        }

        $migration->up();
        foreach ($tables as $table) {
            self::assertTrue(Schema::hasTable($table));
        }
    }

    private function documentsMigration(): ReversibleMigration
    {
        $migration = require database_path(
            'migrations/2026_07_23_000400_create_document_tables.php',
        );

        if (! $migration instanceof ReversibleMigration) {
            throw new LogicException('The documents migration must be reversible.');
        }

        return $migration;
    }
}
