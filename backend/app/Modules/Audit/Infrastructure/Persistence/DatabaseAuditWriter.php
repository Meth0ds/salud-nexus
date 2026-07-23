<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use App\Modules\Audit\Application\AuditEventData;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Infrastructure\Integrity\AuditIntegrity;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;

/**
 * Append events to a per-organization audit chain under a database lock.
 */
final readonly class DatabaseAuditWriter implements AuditWriter
{
    public function __construct(
        private DatabaseManager $database,
        private PublicIdGenerator $ids,
        private ClockInterface $clock,
        private AuditIntegrity $integrity,
    ) {}

    /**
     * Persist one HMAC-linked event and advance the chain head atomically.
     */
    public function record(AuditEventData $event): AuditEvent
    {
        return $this->database->transaction(function () use ($event): AuditEvent {
            $organizationId = $event->actor->organizationId->toString();
            $this->database->table('audit_chain_heads')->insertOrIgnore([
                'organization_public_id' => $organizationId,
                'last_sequence' => 0,
                'last_hash' => null,
            ]);

            /**
             * Preserve the nullable database-row type returned by the query builder.
             *
             * @var object|null $head
             */
            $head = $this->database->table('audit_chain_heads')
                ->where('organization_public_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if ($head === null) {
                throw new RuntimeException('The audit chain head could not be acquired.');
            }

            /**
             * Treat the locked chain head as its scalar persistence payload.
             *
             * @var array<string, mixed> $headData
             */
            $headData = (array) $head;
            $sequence = ((int) ($headData['last_sequence'] ?? 0)) + 1;
            $previousHash = $headData['last_hash'] ?? null;
            $previousHash = is_string($previousHash) ? $previousHash : null;
            $publicId = $this->ids->generate()->toString();
            $occurredAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.u\Z');
            $metadataJson = $this->integrity->metadataJson($event->metadata);

            /**
             * Build the canonical scalar payload covered by the event HMAC.
             *
             * @var array<string, int|string|null> $hashPayload
             */
            $hashPayload = [
                'version' => 1,
                'public_id' => $publicId,
                'organization_public_id' => $organizationId,
                'chain_sequence' => $sequence,
                'actor_public_id' => $event->actor->actorId->toString(),
                'identity_public_id' => $event->actor->identityId->toString(),
                'session_public_id' => $event->actor->sessionId->toString(),
                'center_public_id' => $event->actor->centerId?->toString(),
                'actor_role' => $event->actor->role->value,
                'purpose' => $event->actor->purpose->value,
                'authentication_level' => $event->actor->authenticationLevel->value,
                'action' => $event->action,
                'target_type' => $event->targetType,
                'target_public_id' => $event->targetId?->toString(),
                'result' => $event->result->value,
                'request_id' => $event->requestId->toString(),
                'occurred_at' => $occurredAt,
                'metadata_json' => $metadataJson,
                'previous_hash' => $previousHash,
            ];
            $eventHash = $this->integrity->hash($hashPayload);

            $storedPayload = $hashPayload;
            unset($storedPayload['version']);
            $this->database->table('audit_events')->insert([
                ...$storedPayload,
                'hash_version' => 1,
                'event_hash' => $eventHash,
            ]);
            $this->database->table('audit_chain_heads')
                ->where('organization_public_id', $organizationId)
                ->update(['last_sequence' => $sequence, 'last_hash' => $eventHash]);

            return AuditEvent::query()->where('public_id', $publicId)->firstOrFail();
        }, 3);
    }
}
