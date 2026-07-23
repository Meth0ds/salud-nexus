<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use LogicException;

/**
 * Terminate a stateful browser session and rotate its CSRF token.
 */
final readonly class TerminateBrowserSession
{
    public function __construct(private AuthFactory $auth) {}

    /**
     * Logout the web guard and invalidate all data in the current session.
     */
    public function handle(Request $request): void
    {
        $guard = $this->auth->guard('web');

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('The web authentication guard must be stateful.');
        }

        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
