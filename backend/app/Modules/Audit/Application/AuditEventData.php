<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\AuditOutcome;
use App\Shared\Domain\Identity\ActorContext;
use App\Shared\Domain\Identity\PublicId;
use InvalidArgumentException;

/**
 * Carry a validated, privacy-bounded event into the audit writer.
 */
final readonly class AuditEventData
{
    /**
     * Store metadata that has passed key, size, and sensitivity checks.
     *
     * @var array<string, bool|float|int|string|null>
     */
    public array $metadata;

    /**
     * Create and validate a normalized audit event payload.
     *
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    public function __construct(
        public ActorContext $actor,
        public string $action,
        public string $targetType,
        public ?PublicId $targetId,
        public AuditOutcome $result,
        public PublicId $requestId,
        array $metadata = [],
    ) {
        self::assertIdentifier($action, 'action', 120);
        self::assertIdentifier($targetType, 'target type', 80);

        if (count($metadata) > 16) {
            throw new InvalidArgumentException('Audit metadata may contain at most 16 entries.');
        }

        foreach ($metadata as $key => $value) {
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key) !== 1) {
                throw new InvalidArgumentException('Audit metadata keys must use safe snake_case names.');
            }

            if (preg_match('/(?:name|email|address|birth|diagnos|medicat|document|note|phone)/i', $key) === 1) {
                throw new InvalidArgumentException('Potential personal or clinical data is not allowed in audit metadata.');
            }

            if (is_string($value) && (mb_strlen($value) > 256 || preg_match('/[\r\n]/', $value) === 1)) {
                throw new InvalidArgumentException('Audit metadata strings must be short and single-line.');
            }
        }

        $this->metadata = $metadata;
    }

    /**
     * Validate a bounded machine-readable audit identifier.
     */
    private static function assertIdentifier(string $value, string $label, int $maximum): void
    {
        if (
            strlen($value) > $maximum
            || preg_match('/^[a-z][a-z0-9_.:-]{2,'.($maximum - 1).'}$/D', $value) !== 1
        ) {
            throw new InvalidArgumentException("Audit {$label} is invalid.");
        }
    }
}
