<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Shared\Domain\Identity\AccessPurpose;
use App\Shared\Domain\Identity\ActorContext;
use App\Shared\Domain\Identity\ActorRole;
use App\Shared\Domain\Identity\AuthenticationLevel;
use App\Shared\Domain\Identity\PublicId;
use PHPUnit\Framework\TestCase;

final class ActorContextTest extends TestCase
{
    public function test_it_carries_the_complete_immutable_authorization_context(): void
    {
        $context = new ActorContext(
            actorId: $this->id('018f47a2-4f4a-7b0f-8b15-9f82558b5924'),
            identityId: $this->id('018f47a2-4f4a-7b0f-9b15-9f82558b5925'),
            sessionId: $this->id('018f47a2-4f4a-7b0f-ab15-9f82558b5926'),
            organizationId: $this->id('018f47a2-4f4a-7b0f-bb15-9f82558b5927'),
            centerId: $this->id('018f47a2-4f4a-7b0f-8b15-9f82558b5928'),
            role: ActorRole::Clinician,
            purpose: AccessPurpose::CareDelivery,
            authenticationLevel: AuthenticationLevel::Aal2,
        );

        self::assertSame(ActorRole::Clinician, $context->role);
        self::assertSame(AccessPurpose::CareDelivery, $context->purpose);
        self::assertSame('018f47a2-4f4a-7b0f-bb15-9f82558b5927', $context->organizationId->toString());
        self::assertTrue($context->hasAuthenticationLevel(AuthenticationLevel::Aal1));
        self::assertTrue($context->hasAuthenticationLevel(AuthenticationLevel::Aal2));
        self::assertFalse($context->hasAuthenticationLevel(AuthenticationLevel::Aal3));
    }

    private function id(string $value): PublicId
    {
        return PublicId::fromString($value);
    }
}
