<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

/**
 * Carry the validated reschedule command into the application boundary.
 */
final readonly class RescheduleAppointmentData
{
    public function __construct(
        public string $slotId,
        public int $expectedVersion,
        public string $idempotencyKey,
        public string $requestPublicId,
    ) {}

    /**
     * Produce the canonical payload digest bound to the idempotency key.
     *
     * Public identifiers are normalized before hashing to prevent equivalent
     * UUID spellings from creating different commands.
     */
    public function requestHash(string $appointmentPublicId): string
    {
        return hash('sha256', json_encode([
            'appointment_id' => strtolower($appointmentPublicId),
            'expected_version' => $this->expectedVersion,
            'slot_id' => strtolower($this->slotId),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
