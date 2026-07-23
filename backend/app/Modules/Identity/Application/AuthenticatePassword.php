<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Http\Middleware\AssignRequestId;
use App\Modules\Identity\Domain\IdentityAccountStatus;
use App\Modules\Identity\Domain\IdentitySecurityEventType;
use App\Modules\Identity\Domain\MfaMethodStatus;
use App\Modules\Identity\Domain\MfaMethodType;
use App\Modules\Identity\Domain\SecurityEventOutcome;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Identity\Infrastructure\Persistence\IdentityMfaMethod;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use LogicException;
use SensitiveParameter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Authenticate an active account and establish a hardened stateful session.
 */
final readonly class AuthenticatePassword
{
    public function __construct(
        private AuthFactory $auth,
        private Hasher $hasher,
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
        private MfaChallengeStore $challenges,
        private IdentitySecurityEventWriter $securityEvents,
    ) {}

    /**
     * Attempt password authentication and rotate all session identifiers.
     *
     * The password is marked sensitive so PHP never includes it in stack traces.
     */
    public function handle(
        Request $request,
        string $normalizedEmail,
        #[SensitiveParameter] string $password,
    ): PasswordAuthenticationOutcome {
        $guard = $this->auth->guard('web');

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('The web authentication guard must be stateful.');
        }

        if ($guard->check()) {
            throw new ConflictHttpException;
        }

        $credentials = [
            'email' => $normalizedEmail,
            'password' => $password,
            'status' => IdentityAccountStatus::Active->value,
        ];

        if (! $guard->validate($credentials)) {
            throw new AuthenticationException(guards: ['web']);
        }

        $account = IdentityAccount::query()
            ->where('email', $normalizedEmail)
            ->where('status', IdentityAccountStatus::Active->value)
            ->first();

        if (! $account instanceof IdentityAccount) {
            throw new LogicException('Validated credentials must resolve an active identity account.');
        }

        if ($this->hasher->needsRehash($account->password)) {
            $account->password = $password;
            $account->save();
        }

        $mfaMethod = IdentityMfaMethod::query()
            ->where('identity_account_id', $account->id)
            ->where('type', MfaMethodType::Totp->value)
            ->where('status', MfaMethodStatus::Active->value)
            ->first();

        if ($mfaMethod instanceof IdentityMfaMethod) {
            $request->session()->regenerate(true);
            $request->session()->forget([
                BrowserSession::METHOD,
                BrowserSession::LEVEL,
                BrowserSession::AUTHENTICATED_AT,
                BrowserSession::PASSWORD_AUTHENTICATED_AT,
                BrowserSession::PUBLIC_ID,
                BrowserSession::ASSURANCE_SCOPE,
            ]);
            $challenge = $this->challenges->issueLogin($request, $account, $mfaMethod);
            $this->securityEvents->write(
                account: $account,
                type: IdentitySecurityEventType::MfaChallengeIssued,
                outcome: SecurityEventOutcome::Succeeded,
                authenticationLevel: 1,
                requestPublicId: $this->requestPublicId($request),
                metadata: [
                    'attempts_remaining' => $challenge->attemptsRemaining,
                    'intent' => $challenge->intent->value,
                ],
            );

            return new PasswordAuthenticationOutcome($challenge);
        }

        $guard->login($account, false);
        $authenticatedAt = $this->clock->now()->format(DATE_ATOM);
        $request->session()->put([
            BrowserSession::METHOD => 'password',
            BrowserSession::LEVEL => 1,
            BrowserSession::AUTHENTICATED_AT => $authenticatedAt,
            BrowserSession::PASSWORD_AUTHENTICATED_AT => $authenticatedAt,
            BrowserSession::PUBLIC_ID => $this->publicIds->generate()->toString(),
        ]);

        return new PasswordAuthenticationOutcome(null);
    }

    /**
     * Return the request identifier assigned before authentication begins.
     */
    private function requestPublicId(Request $request): string
    {
        $requestPublicId = $request->attributes->get(AssignRequestId::ATTRIBUTE);

        if (! is_string($requestPublicId)) {
            throw new LogicException('Password authentication requires a request identifier.');
        }

        return $requestPublicId;
    }
}
