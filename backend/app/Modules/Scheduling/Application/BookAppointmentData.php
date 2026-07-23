<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

/**
 * Carry validated booking identifiers and idempotency metadata.
 */
final readonly class BookAppointmentData
{
    public function __construct(
        public string $appointmentTypeId,
        public string $slotId,
        public string $idempotencyKey,
    ) {}

    /**
     * Produce the canonical payload digest used to bind an idempotency key.
     */
    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'appointment_type_id' => strtolower($this->appointmentTypeId),
            'slot_id' => strtolower($this->slotId),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
