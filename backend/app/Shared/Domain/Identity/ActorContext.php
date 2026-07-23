<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Carry immutable actor, tenant, session, role, purpose, and assurance context.
 */
final readonly class ActorContext
{
    public function __construct(
        public PublicId $actorId,
        public PublicId $identityId,
        public PublicId $sessionId,
        public PublicId $organizationId,
        public ?PublicId $centerId,
        public ActorRole $role,
        public AccessPurpose $purpose,
        public AuthenticationLevel $authenticationLevel,
    ) {}

    /**
     * Determine whether the session meets or exceeds the required assurance level.
     */
    public function hasAuthenticationLevel(AuthenticationLevel $required): bool
    {
        return $this->authenticationLevel->value >= $required->value;
    }
}
