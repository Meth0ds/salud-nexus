<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application;

use App\Modules\Identity\Application\BrowserSession;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Shared\Domain\Identity\AccessPurpose;
use App\Shared\Domain\Identity\ActorContext;
use App\Shared\Domain\Identity\ActorRole;
use App\Shared\Domain\Identity\AuthenticationLevel;
use App\Shared\Domain\Identity\PublicId;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Http\Request;

/**
 * Build the normalized actor context attached to patient portal audit events.
 */
final readonly class PortalActorContextFactory
{
    public function __construct(private PublicIdGenerator $publicIds) {}

    /**
     * Create an actor context while ensuring the browser session has a public ID.
     */
    public function make(Request $request, IdentityAccount $identity, Patient $patient): ActorContext
    {
        $sessionId = $request->session()->get(BrowserSession::PUBLIC_ID);

        if (! is_string($sessionId)) {
            $sessionId = $this->publicIds->generate()->toString();
            $request->session()->put(BrowserSession::PUBLIC_ID, $sessionId);
        }

        return new ActorContext(
            actorId: PublicId::fromString($patient->public_id),
            identityId: PublicId::fromString($identity->public_id),
            sessionId: PublicId::fromString($sessionId),
            organizationId: PublicId::fromString($patient->organization->public_id),
            centerId: $patient->homeCenter === null
                ? null
                : PublicId::fromString($patient->homeCenter->public_id),
            role: ActorRole::Patient,
            purpose: AccessPurpose::PatientSelfService,
            authenticationLevel: AuthenticationLevel::Aal1,
        );
    }
}
