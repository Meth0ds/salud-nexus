<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Identify the authentication ceremony authorized by an MFA challenge.
 */
enum MfaChallengeIntent: string
{
    case Login = 'login';
    case StepUp = 'step_up';
}
