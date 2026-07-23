<?php

declare(strict_types=1);

namespace App\Support\Health;

/**
 * Carry a sanitized readiness state and its dependency checks.
 */
final readonly class ReadinessResult
{
    /**
     * Create an immutable readiness result.
     *
     * @param  array<string, string>  $checks
     */
    private function __construct(
        public bool $ready,
        public array $checks,
    ) {}

    /**
     * Create a successful readiness result.
     */
    public static function ready(): self
    {
        return new self(true, ['database' => 'ok']);
    }

    /**
     * Create a failed readiness result for the named dependency.
     */
    public static function notReady(string $dependency): self
    {
        return new self(false, [$dependency => 'unavailable']);
    }
}
