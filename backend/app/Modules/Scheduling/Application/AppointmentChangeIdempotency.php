<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Scheduling\Infrastructure\Persistence\IdempotencyRequest;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Coordinate idempotency records shared by appointment-change operations.
 *
 * The persisted request hash binds a key to one command payload, while the
 * immutable change public ID allows an exact response to be reconstructed.
 */
final readonly class AppointmentChangeIdempotency
{
    /**
     * Find an actor-scoped idempotency record for the named operation.
     *
     * A pessimistic lock is requested by mutation transactions so concurrent
     * retries cannot both advance the same command.
     */
    public function find(
        IdentityAccount $account,
        string $operation,
        string $key,
        bool $lock,
    ): ?IdempotencyRequest {
        $query = IdempotencyRequest::query()
            ->where('identity_account_id', $account->id)
            ->where('route', $operation)
            ->where('idempotency_key', $key);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Start tracking a command before any appointment state is mutated.
     */
    public function start(
        IdentityAccount $account,
        string $operation,
        string $key,
        string $requestHash,
        DateTimeImmutable $expiresAt,
    ): IdempotencyRequest {
        return IdempotencyRequest::query()->create([
            'identity_account_id' => $account->id,
            'route' => $operation,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'status' => 'processing',
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Mark a command as committed and retain its immutable replay target.
     */
    public function complete(
        IdempotencyRequest $request,
        string $replayPublicId,
        DateTimeImmutable $completedAt,
    ): void {
        // Store the immutable transition identifier, not a pointer to mutable current state.
        $request->forceFill([
            'status' => 'completed',
            'response_status' => 200,
            'resource_public_id' => $replayPublicId,
            'completed_at' => $completedAt,
        ])->save();
    }

    /**
     * Resolve a completed replay target after verifying payload equivalence.
     *
     * @throws ConflictHttpException When the key was reused for another payload
     *                               or the original command never committed.
     */
    public function replayResource(IdempotencyRequest $request, string $requestHash): string
    {
        if (! hash_equals($request->request_hash, $requestHash)) {
            throw new ConflictHttpException;
        }

        if (
            $request->status !== 'completed'
            || $request->response_status !== 200
            || ! is_string($request->resource_public_id)
        ) {
            throw new ConflictHttpException;
        }

        return $request->resource_public_id;
    }
}
