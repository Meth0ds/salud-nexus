<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Support\Database\ReversibleMigration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class PatientBookingMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * List the tables owned by the patient booking migration.
     *
     * @var list<string>
     */
    private const TABLES = [
        'organizations',
        'centers',
        'patients',
        'patient_portal_links',
        'health_services',
        'appointment_types',
        'appointment_slots',
        'appointments',
        'idempotency_requests',
    ];

    public function test_patient_booking_schema_migrates_down_and_up_cleanly(): void
    {
        $migration = $this->patientBookingMigration();

        $migration->down();

        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table));
        }

        $migration->up();

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table));
        }

        self::assertTrue(Schema::hasColumns('appointments', [
            'public_id',
            'organization_id',
            'patient_id',
            'slot_id',
            'attendance_mode',
            'center_timezone',
            'starts_at',
            'ends_at',
        ]));
    }

    private function patientBookingMigration(): ReversibleMigration
    {
        $migration = require database_path(
            'migrations/2026_07_19_000100_create_patient_booking_tables.php',
        );

        if (! $migration instanceof ReversibleMigration) {
            throw new LogicException('The patient booking migration must be reversible.');
        }

        return $migration;
    }
}
