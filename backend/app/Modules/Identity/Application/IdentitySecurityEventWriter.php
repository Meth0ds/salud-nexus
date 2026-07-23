<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\IdentitySecurityEventType;
use App\Modules\Identity\Domain\SecurityEventOutcome;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentitySecurityEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Clock\ClockInterface;

/**
 * Persist minimized authentication events without credentials, PII, or PHI.
 */
final readonly class IdentitySecurityEventWriter
{
    private const ALLOWED_METADATA_KEYS = [
        'attempts_remaining',
        'factor',
        'intent',
        'purpose',
        'reason',
        'recovery_codes_remaining',
    ];

    /**
     * Create the security event writer with an injectable UTC clock.
     */
    public function __construct(private ClockInterface $clock) {}

    /**
     * Persist one normalized append-only identity security event.
     *
     * @param  array<string, int|string|null>  $metadata
     *
     * @throws JsonException
     */
    public function write(
        ?IdentityAccount $account,
        IdentitySecurityEventType $type,
        SecurityEventOutcome $outcome,
        int $authenticationLevel,
        string $requestPublicId,
        array $metadata = [],
    ): IdentitySecurityEvent {
        if (
            $authenticationLevel < 0
            || $authenticationLevel > 2
            || ! Str::isUuid($requestPublicId)
        ) {
            throw new InvalidArgumentException('The identity security event context is invalid.');
        }

        foreach ($metadata as $key => $value) {
            if (
                ! in_array($key, self::ALLOWED_METADATA_KEYS, true)
                || (is_string($value) && (
                    strlen($value) > 64
                    || preg_match('/^[a-z0-9_.:-]+$/D', $value) !== 1
                ))
                || (is_int($value) && ($value < 0 || $value > 1000))
            ) {
                throw new InvalidArgumentException('Identity security event metadata is not allowlisted.');
            }
        }

        ksort($metadata, SORT_STRING);
        $event = new IdentitySecurityEvent;
        $event->identity_account_id = $account?->id;
        $event->request_public_id = strtolower($requestPublicId);
        $event->event_type = $type->value;
        $event->result = $outcome->value;
        $event->authentication_level = $authenticationLevel;
        $event->metadata_json = json_encode(
            $metadata,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $event->occurred_at = CarbonImmutable::instance($this->clock->now());
        $event->save();

        return $event;
    }
}
