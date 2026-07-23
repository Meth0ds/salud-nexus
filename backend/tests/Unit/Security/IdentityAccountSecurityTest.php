<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

final class IdentityAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_accounts_are_not_patient_records_and_use_public_uuid_v7_ids(): void
    {
        $account = IdentityAccount::factory()->create();

        self::assertTrue(Str::isUuid($account->public_id, 7));
        self::assertTrue(Schema::hasTable('identity_accounts'));
        self::assertFalse(Schema::hasColumn('identity_accounts', 'patient_id'));
        self::assertSame(IdentityAccount::class, config('auth.providers.identities.model'));
    }

    public function test_passwords_are_argon2id_hashed_and_hidden_from_serialization(): void
    {
        $plainPassword = 'Long-and-unique-password-2026!';
        $account = IdentityAccount::factory()->create(['password' => $plainPassword]);

        self::assertSame('argon2id', password_get_info($account->getAuthPassword())['algoName']);
        self::assertTrue(Hash::check($plainPassword, $account->getAuthPassword()));
        self::assertArrayNotHasKey('password', $account->toArray());
        self::assertArrayNotHasKey('remember_token', $account->toArray());
    }

    public function test_session_and_hashing_configuration_have_fail_safe_defaults(): void
    {
        self::assertSame('argon2id', config('hashing.driver'));
        self::assertTrue(config()->boolean('session.encrypt'));
        self::assertTrue(config()->boolean('session.http_only'));
        self::assertSame('lax', config('session.same_site'));
        self::assertSame('json', config('session.serialization'));

        $exampleEnvironment = file_get_contents(base_path('.env.example'));
        self::assertIsString($exampleEnvironment);
        self::assertStringContainsString('SESSION_DRIVER=database', $exampleEnvironment);
    }

    public function test_sanctum_is_stateful_and_limited_to_the_web_guard(): void
    {
        self::assertSame(['web'], config('sanctum.guard'));
        self::assertFalse(config()->boolean('sanctum.routes'));
        self::assertContains('localhost:4200', config('sanctum.stateful'));
        self::assertNotContains('*', config('sanctum.stateful'));

        $apiMiddleware = $this->app->make(Router::class)->getMiddlewareGroups()['api'];

        self::assertContains(EnsureFrontendRequestsAreStateful::class, $apiMiddleware);
    }
}
