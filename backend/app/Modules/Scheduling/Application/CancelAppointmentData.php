<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Domain\AppointmentCancellationReason;

/**
 * Carry the validated cancellation command into the application boundary.
 */
final readonly class CancelAppointmentData
{
    public function __construct(
        public AppointmentCancellationReason $reason,
        public int $expectedVersion,
        public string $idempotencyKey,
        public string $requestPublicId,
    ) {}

    /**
     * Produce the canonical payload digest bound to the idempotency key.
     *
     * Transport-only values are deliberately excluded so equivalent retries
     * resolve to the same committed command.
     */
    public function requestHash(string $appointmentPublicId): string
    {
        return hash('sha256', json_encode([
            'appointment_id' => strtolower($appointmentPublicId),
            'expected_version' => $this->expectedVersion,
            'reason_code' => $this->reason->value,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
