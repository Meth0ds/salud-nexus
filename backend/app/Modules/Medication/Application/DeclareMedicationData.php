<?php

declare(strict_types=1);

namespace App\Modules\Medication\Application;

/**
 * Carry a validated patient medication declaration into the application layer.
 */
final readonly class DeclareMedicationData
{
    public function __construct(
        public string $name,
        public ?string $presentation,
        public string $scheduleLabel,
        public string $idempotencyKey,
    ) {}

    /**
     * Produce the canonical payload digest bound to the idempotency key.
     */
    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'name' => $this->name,
            'presentation' => $this->presentation,
            'schedule_label' => $this->scheduleLabel,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
