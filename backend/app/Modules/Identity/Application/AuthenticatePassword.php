<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\IdentityAccountStatus;
use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Shared\Domain\Identity\PublicIdGenerator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
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
        private ClockInterface $clock,
        private PublicIdGenerator $publicIds,
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
    ): IdentityAccount {
        $guard = $this->auth->guard('web');

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('The web authentication guard must be stateful.');
        }

        if ($guard->check()) {
            throw new ConflictHttpException;
        }

        $authenticated = $guard->attempt([
            'email' => $normalizedEmail,
            'password' => $password,
            'status' => IdentityAccountStatus::Active->value,
        ], false);

        if (! $authenticated) {
            throw new AuthenticationException(guards: ['web']);
        }

        $account = $guard->user();

        if (! $account instanceof IdentityAccount) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new LogicException('The web guard returned an unsupported identity type.');
        }

        $request->session()->regenerate();
        $authenticatedAt = $this->clock->now()->format(DATE_ATOM);
        $request->session()->put([
            BrowserSession::METHOD => 'password',
            BrowserSession::LEVEL => 1,
            BrowserSession::AUTHENTICATED_AT => $authenticatedAt,
            BrowserSession::PASSWORD_AUTHENTICATED_AT => $authenticatedAt,
            BrowserSession::PUBLIC_ID => $this->publicIds->generate()->toString(),
        ]);

        return $account;
    }
}
