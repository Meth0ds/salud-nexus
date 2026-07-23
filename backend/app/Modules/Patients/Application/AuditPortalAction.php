<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Audit\Application\AuditEventData;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Domain\AuditOutcome;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Shared\Domain\Identity\PublicId;
use Illuminate\Http\Request;
use LogicException;

/**
 * Write consistently attributed, integrity-protected patient portal audit events.
 */
final readonly class AuditPortalAction
{
    public function __construct(
        private AuditWriter $audit,
        private PortalActorContextFactory $actors,
    ) {}

    /**
     * Record a successful portal action.
     *
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    public function succeeded(
        Request $request,
        IdentityAccount $identity,
        Patient $patient,
        string $action,
        string $targetType,
        string $targetId,
        array $metadata = [],
    ): void {
        $this->record(
            $request,
            $identity,
            $patient,
            $action,
            $targetType,
            $targetId,
            AuditOutcome::Succeeded,
            $metadata,
        );
    }

    /**
     * Record an authorization denial without exposing sensitive context.
     *
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    public function denied(
        Request $request,
        IdentityAccount $identity,
        Patient $patient,
        string $action,
        string $targetType,
        string $targetId,
        array $metadata = [],
    ): void {
        $this->record(
            $request,
            $identity,
            $patient,
            $action,
            $targetType,
            $targetId,
            AuditOutcome::Denied,
            $metadata,
        );
    }

    /**
     * Record a failed command after its outcome has been classified.
     *
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    public function failed(
        Request $request,
        IdentityAccount $identity,
        Patient $patient,
        string $action,
        string $targetType,
        string $targetId,
        array $metadata = [],
    ): void {
        $this->record(
            $request,
            $identity,
            $patient,
            $action,
            $targetType,
            $targetId,
            AuditOutcome::Failed,
            $metadata,
        );
    }

    /**
     * Normalize request attribution and append the event to the audit chain.
     *
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    private function record(
        Request $request,
        IdentityAccount $identity,
        Patient $patient,
        string $action,
        string $targetType,
        string $targetId,
        AuditOutcome $outcome,
        array $metadata,
    ): void {
        $requestId = $request->attributes->get(AssignRequestId::ATTRIBUTE);

        if (! is_string($requestId)) {
            throw new LogicException('An audit event requires a request identifier.');
        }

        $this->audit->record(new AuditEventData(
            actor: $this->actors->make($request, $identity, $patient),
            action: $action,
            targetType: $targetType,
            targetId: PublicId::fromString($targetId),
            result: $outcome,
            requestId: PublicId::fromString($requestId),
            metadata: $metadata,
        ));
    }
}
