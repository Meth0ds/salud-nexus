<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Represent a canonical UUIDv7 safe for use at public application boundaries.
 */
final readonly class PublicId implements JsonSerializable, Stringable
{
    private const UUID_V7_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private function __construct(private string $value) {}

    /**
     * Parse and normalize a canonical UUIDv7 value.
     */
    public static function fromString(string $value): self
    {
        $canonical = strtolower($value);

        if (preg_match(self::UUID_V7_PATTERN, $canonical) !== 1) {
            throw new InvalidArgumentException('Public identifier must be a canonical UUIDv7.');
        }

        return new self($canonical);
    }

    /**
     * Compare identifiers using a timing-safe equality operation.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    /**
     * Return the canonical lowercase representation.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Serialize the identifier without exposing internal database keys.
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * Return the canonical lowercase representation.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
