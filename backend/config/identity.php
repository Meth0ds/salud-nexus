<?php

declare(strict_types=1);

return [
    'rate_limits' => [
        'login_account_ip_per_minute' => (int) env('AUTH_LOGIN_ACCOUNT_IP_PER_MINUTE', 5),
        'login_ip_per_minute' => (int) env('AUTH_LOGIN_IP_PER_MINUTE', 20),
        'mfa_per_minute' => (int) env('AUTH_MFA_PER_MINUTE', 10),
        'mfa_challenge_per_minute' => (int) env('AUTH_MFA_CHALLENGE_PER_MINUTE', 10),
    ],

    'mfa' => [
        'totp' => [
            'issuer' => (string) env('MFA_TOTP_ISSUER', env('APP_NAME', 'Healthcare Center')),
            'secret_length' => (int) env('MFA_TOTP_SECRET_LENGTH', 32),
            'digits' => 6,
            'period_seconds' => 30,
            'window' => (int) env('MFA_TOTP_WINDOW', 1),
        ],
        'enrollment_ttl_minutes' => (int) env('MFA_ENROLLMENT_TTL_MINUTES', 5),
        'challenge_ttl_minutes' => (int) env('MFA_CHALLENGE_TTL_MINUTES', 10),
        'max_attempts' => (int) env('MFA_CHALLENGE_MAX_ATTEMPTS', 5),
        'password_freshness_minutes' => (int) env('MFA_PASSWORD_FRESHNESS_MINUTES', 10),
        'recovery_code_count' => (int) env('MFA_RECOVERY_CODE_COUNT', 10),
        'recovery_code_length' => (int) env('MFA_RECOVERY_CODE_LENGTH', 24),
        'recovery_lookup_key' => (string) env('MFA_RECOVERY_LOOKUP_KEY', env('APP_KEY', '')),
        'aal2_freshness_minutes' => (int) env('MFA_AAL2_FRESHNESS_MINUTES', 10),
    ],
];
