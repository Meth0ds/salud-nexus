<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Infrastructure\Persistence\Appointment;
use App\Support\Database\ReversibleMigration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * Verify the reversible storage boundary for patient-initiated appointment changes.
 */
final class AppointmentChangeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_migrates_down_and_up_and_backfills_scheduled_appointments(): void
    {
        $migration = $this->appointmentChangeMigration();

        self::assertTrue(Schema::hasTable('appointment_slot_allocations'));
        self::assertTrue(Schema::hasTable('appointment_changes'));
        self::assertTrue(Schema::hasColumn('appointments', 'version'));

        $migration->down();

        self::assertFalse(Schema::hasTable('appointment_slot_allocations'));
        self::assertFalse(Schema::hasTable('appointment_changes'));
        self::assertFalse(Schema::hasColumn('appointments', 'version'));

        $appointment = Appointment::factory()->create([
            'status' => AppointmentStatus::Scheduled,
        ]);

        $migration->up();

        $this->assertDatabaseHas('appointment_slot_allocations', [
            'organization_id' => $appointment->organization_id,
            'appointment_id' => $appointment->id,
            'slot_id' => $appointment->slot_id,
        ]);
        self::assertSame(1, $appointment->fresh()?->version);
    }

    public function test_active_allocations_are_unique_for_both_appointment_and_slot(): void
    {
        $first = Appointment::factory()->create(['status' => AppointmentStatus::Cancelled]);
        $second = Appointment::factory()->create([
            'organization_id' => $first->organization_id,
            'center_id' => $first->center_id,
            'appointment_type_id' => $first->appointment_type_id,
            'slot_id' => $first->slot_id,
            'status' => AppointmentStatus::Cancelled,
        ]);

        DB::table('appointment_slot_allocations')->insert([
            'organization_id' => $first->organization_id,
            'appointment_id' => $first->id,
            'slot_id' => $first->slot_id,
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('appointment_slot_allocations')->insert([
            'organization_id' => $second->organization_id,
            'appointment_id' => $second->id,
            'slot_id' => $second->slot_id,
            'created_at' => now(),
        ]);
    }

    private function appointmentChangeMigration(): ReversibleMigration
    {
        $migration = require database_path(
            'migrations/2026_07_23_000500_create_appointment_change_tables.php',
        );

        if (! $migration instanceof ReversibleMigration) {
            throw new LogicException('The appointment change migration must be reversible.');
        }

        return $migration;
    }
}
