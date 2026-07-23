<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use App\Modules\Audit\Application\AuditChainVerification;
use App\Modules\Audit\Application\AuditChainVerifier;
use App\Modules\Audit\Infrastructure\Integrity\AuditIntegrity;
use App\Shared\Domain\Identity\PublicId;
use Illuminate\Database\DatabaseManager;

/**
 * Verify sequence continuity and HMAC linkage against persisted audit data.
 */
final readonly class DatabaseAuditChainVerifier implements AuditChainVerifier
{
    public function __construct(
        private DatabaseManager $database,
        private AuditIntegrity $integrity,
    ) {}

    /**
     * Verify every event and chain-head value for one organization.
     */
    public function verify(PublicId $organizationId): AuditChainVerification
    {
        $events = $this->database->table('audit_events')
            ->where('organization_public_id', $organizationId->toString())
            ->orderBy('chain_sequence')
            ->get();
        $expectedSequence = 1;
        $previousHash = null;

        foreach ($events as $event) {
            /**
             * Treat the database row as its scalar audit payload.
             *
             * @var array<string, mixed> $record
             */
            $record = (array) $event;
            $sequence = (int) ($record['chain_sequence'] ?? 0);

            if (($record['hash_version'] ?? null) !== 1) {
                return new AuditChainVerification(false, $expectedSequence - 1, $sequence, 'unsupported_hash_version');
            }

            if ($sequence !== $expectedSequence) {
                return new AuditChainVerification(false, $expectedSequence - 1, $sequence, 'sequence_gap');
            }

            $storedPreviousHash = $record['previous_hash'] ?? null;
            $storedPreviousHash = is_string($storedPreviousHash) ? $storedPreviousHash : null;
            if ($storedPreviousHash !== $previousHash) {
                return new AuditChainVerification(false, $expectedSequence - 1, $sequence, 'previous_hash_mismatch');
            }

            $storedEventHash = $record['event_hash'] ?? null;
            if (! is_string($storedEventHash)) {
                return new AuditChainVerification(false, $expectedSequence - 1, $sequence, 'missing_event_hash');
            }

            $calculatedHash = $this->integrity->hash(self::hashPayload($record));
            if (! hash_equals($storedEventHash, $calculatedHash)) {
                return new AuditChainVerification(false, $expectedSequence - 1, $sequence, 'event_hash_mismatch');
            }

            $previousHash = $storedEventHash;
            $expectedSequence++;
        }

        $head = $this->database->table('audit_chain_heads')
            ->where('organization_public_id', $organizationId->toString())
            ->first();
        if ($head !== null) {
            /**
             * Treat the locked chain head as its scalar persistence payload.
             *
             * @var array<string, mixed> $headData
             */
            $headData = (array) $head;
            if (
                (int) ($headData['last_sequence'] ?? -1) !== $expectedSequence - 1
                || ($headData['last_hash'] ?? null) !== $previousHash
            ) {
                return new AuditChainVerification(false, $expectedSequence - 1, null, 'head_mismatch');
            }
        }

        return new AuditChainVerification(true, $expectedSequence - 1);
    }

    /**
     * Rebuild the exact canonical payload covered by the stored HMAC.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, int|string|null>
     */
    private static function hashPayload(array $record): array
    {
        return [
            'version' => 1,
            'public_id' => self::stringValue($record, 'public_id'),
            'organization_public_id' => self::stringValue($record, 'organization_public_id'),
            'chain_sequence' => (int) ($record['chain_sequence'] ?? 0),
            'actor_public_id' => self::stringValue($record, 'actor_public_id'),
            'identity_public_id' => self::stringValue($record, 'identity_public_id'),
            'session_public_id' => self::stringValue($record, 'session_public_id'),
            'center_public_id' => self::nullableStringValue($record, 'center_public_id'),
            'actor_role' => self::stringValue($record, 'actor_role'),
            'purpose' => self::stringValue($record, 'purpose'),
            'authentication_level' => (int) ($record['authentication_level'] ?? 0),
            'action' => self::stringValue($record, 'action'),
            'target_type' => self::stringValue($record, 'target_type'),
            'target_public_id' => self::nullableStringValue($record, 'target_public_id'),
            'result' => self::stringValue($record, 'result'),
            'request_id' => self::stringValue($record, 'request_id'),
            'occurred_at' => self::stringValue($record, 'occurred_at'),
            'metadata_json' => self::stringValue($record, 'metadata_json'),
            'previous_hash' => self::nullableStringValue($record, 'previous_hash'),
        ];
    }

    /**
     * Read a required scalar string from a database record.
     *
     * @param  array<string, mixed>  $record
     */
    private static function stringValue(array $record, string $key): string
    {
        $value = $record[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * Read an optional scalar string from a database record.
     *
     * @param  array<string, mixed>  $record
     */
    private static function nullableStringValue(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
