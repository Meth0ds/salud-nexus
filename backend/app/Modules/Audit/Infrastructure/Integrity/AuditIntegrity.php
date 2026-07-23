<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Integrity;

use JsonException;
use LogicException;

/**
 * Canonicalize and authenticate audit data with a dedicated HMAC key.
 */
final readonly class AuditIntegrity
{
    private string $key;

    /**
     * Decode and validate the dedicated audit integrity key.
     */
    public function __construct(?string $encodedKey)
    {
        $decoded = is_string($encodedKey) ? base64_decode($encodedKey, true) : false;

        if (! is_string($decoded) || strlen($decoded) < 32) {
            throw new LogicException('AUDIT_INTEGRITY_KEY must be a base64-encoded key of at least 32 bytes.');
        }

        $this->key = $decoded;
    }

    /**
     * Calculate the HMAC for one canonical audit-chain payload.
     *
     * @param  array<string, int|string|null>  $payload
     */
    public function hash(array $payload): string
    {
        return hash_hmac('sha256', self::canonicalJson($payload), $this->key);
    }

    /**
     * Canonicalize privacy-bounded metadata before it enters the hash chain.
     *
     * @param  array<string, bool|float|int|string|null>  $metadata
     *
     * @throws JsonException
     */
    public function metadataJson(array $metadata): string
    {
        ksort($metadata, SORT_STRING);

        return self::canonicalJson($metadata);
    }

    /**
     * Encode an associative payload using deterministic JSON options.
     *
     * @param  array<string, int|string|null>|array<string, bool|float|int|string|null>  $value
     *
     * @throws JsonException
     */
    private static function canonicalJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE,
        );
    }
}
