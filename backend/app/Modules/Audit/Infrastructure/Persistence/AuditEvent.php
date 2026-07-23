<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Persist one immutable, integrity-linked security audit event.
 *
 * @property int $id
 * @property string $public_id
 * @property string $organization_public_id
 * @property int $chain_sequence
 * @property string $action
 * @property string $target_type
 * @property string|null $target_public_id
 * @property string $result
 * @property string $request_id
 * @property string $metadata_json
 * @property string|null $previous_hash
 * @property string $event_hash
 */
#[Guarded(['*'])]
final class AuditEvent extends Model
{
    public $timestamps = false;

    /**
     * Register guards that keep the audit chain append-only.
     */
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Audit events are append-only and cannot be updated.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Audit events are append-only and cannot be deleted.');
        });
    }
}
