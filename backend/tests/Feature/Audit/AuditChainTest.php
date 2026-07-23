<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Audit\Application\AuditChainVerifier;
use App\Modules\Audit\Application\AuditEventData;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Domain\AuditOutcome;
use App\Modules\Audit\Infrastructure\Integrity\AuditIntegrity;
use App\Shared\Domain\Identity\AccessPurpose;
use App\Shared\Domain\Identity\ActorContext;
use App\Shared\Domain\Identity\ActorRole;
use App\Shared\Domain\Identity\AuthenticationLevel;
use App\Shared\Domain\Identity\PublicId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

final class AuditChainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('audit.integrity_key', base64_encode(str_repeat('audit-test-key-', 3)));
        $this->app->forgetInstance(AuditIntegrity::class);
    }

    public function test_events_are_minimized_chained_per_tenant_and_verifiable(): void
    {
        $actor = $this->actor();
        $writer = $this->app->make(AuditWriter::class);
        $first = $writer->record($this->event($actor, 'patient.profile.viewed', [
            'reason_code' => 'self_service',
            'policy_id' => 'patient-self-v1',
        ]));
        $second = $writer->record($this->event($actor, 'appointment.booked'));

        self::assertSame(1, $first->chain_sequence);
        self::assertNull($first->previous_hash);
        self::assertSame(2, $second->chain_sequence);
        self::assertSame($first->event_hash, $second->previous_hash);
        self::assertSame(
            '{"policy_id":"patient-self-v1","reason_code":"self_service"}',
            $first->metadata_json,
        );
        self::assertSame(64, strlen($first->event_hash));

        $verification = $this->app->make(AuditChainVerifier::class)
            ->verify($actor->organizationId);
        self::assertTrue($verification->valid);
        self::assertSame(2, $verification->checkedEvents);
        self::assertNull($verification->reason);
    }

    public function test_database_tampering_is_detected_without_disclosing_event_data(): void
    {
        $actor = $this->actor();
        $event = $this->app->make(AuditWriter::class)
            ->record($this->event($actor, 'patient.profile.viewed'));

        DB::table('audit_events')->where('id', $event->id)->update(['result' => 'failed']);

        $verification = $this->app->make(AuditChainVerifier::class)
            ->verify($actor->organizationId);
        self::assertFalse($verification->valid);
        self::assertSame(0, $verification->checkedEvents);
        self::assertSame(1, $verification->brokenSequence);
        self::assertSame('event_hash_mismatch', $verification->reason);
    }

    public function test_each_organization_has_an_independent_genesis_event(): void
    {
        $writer = $this->app->make(AuditWriter::class);
        $first = $writer->record($this->event($this->actor(), 'patient.profile.viewed'));
        $second = $writer->record($this->event($this->actor(), 'patient.profile.viewed'));

        self::assertSame(1, $first->chain_sequence);
        self::assertSame(1, $second->chain_sequence);
        self::assertNull($first->previous_hash);
        self::assertNull($second->previous_hash);
    }

    public function test_eloquent_updates_to_audit_events_are_rejected(): void
    {
        $event = $this->app->make(AuditWriter::class)
            ->record($this->event($this->actor(), 'patient.profile.viewed'));
        $event->action = 'patient.profile.denied';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('append-only');
        $event->save();
    }

    public function test_eloquent_deletes_of_audit_events_are_rejected(): void
    {
        $event = $this->app->make(AuditWriter::class)
            ->record($this->event($this->actor(), 'patient.profile.viewed'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('append-only');
        $event->delete();
    }

    public function test_potential_personal_or_clinical_metadata_is_rejected_before_storage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('personal or clinical');

        $this->event($this->actor(), 'patient.profile.viewed', ['patient_name' => 'Not allowed']);
    }

    public function test_integrity_key_must_decode_to_at_least_256_bits(): void
    {
        $this->expectException(LogicException::class);
        new AuditIntegrity(base64_encode('short'));
    }

    /**
     * Build a normalized audit event for the requested action.
     *
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    private function event(ActorContext $actor, string $action, array $metadata = []): AuditEventData
    {
        return new AuditEventData(
            actor: $actor,
            action: $action,
            targetType: 'patient',
            targetId: $this->id(),
            result: AuditOutcome::Succeeded,
            requestId: $this->id(),
            metadata: $metadata,
        );
    }

    private function actor(): ActorContext
    {
        return new ActorContext(
            actorId: $this->id(),
            identityId: $this->id(),
            sessionId: $this->id(),
            organizationId: $this->id(),
            centerId: $this->id(),
            role: ActorRole::Patient,
            purpose: AccessPurpose::PatientSelfService,
            authenticationLevel: AuthenticationLevel::Aal1,
        );
    }

    private function id(): PublicId
    {
        return PublicId::fromString(Str::uuid7()->toString());
    }
}
