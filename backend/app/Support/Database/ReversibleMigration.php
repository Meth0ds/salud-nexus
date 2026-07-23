<?php

declare(strict_types=1);

namespace App\Support\Database;

/**
 * Require application migrations to provide an explicit rollback path.
 */
interface ReversibleMigration
{
    /**
     * Apply the schema transition.
     */
    public function up(): void;

    /**
     * Reverse the schema transition.
     */
    public function down(): void;
}
