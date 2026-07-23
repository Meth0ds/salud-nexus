<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

/**
 * Define the server-owned keys stored in an authenticated browser session.
 */
final class BrowserSession
{
    /**
     * Store the authentication methods proven by the current session.
     */
    public const METHOD = 'auth.method';

    /**
     * Store the numeric authentication assurance level.
     */
    public const LEVEL = 'auth.level';

    /**
     * Store when the current assurance level was established.
     */
    public const AUTHENTICATED_AT = 'auth.authenticated_at';

    /**
     * Store when the account password was most recently verified.
     */
    public const PASSWORD_AUTHENTICATED_AT = 'auth.password_authenticated_at';

    /**
     * Store the public identifier used to correlate security events.
     */
    public const PUBLIC_ID = 'auth.session_public_id';

    /**
     * Prevent construction of this session key namespace.
     */
    private function __construct() {}
}
