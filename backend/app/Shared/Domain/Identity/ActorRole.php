<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Enumerate normalized actor roles used by authorization and auditing.
 */
enum ActorRole: string
{
    case Patient = 'patient';
    case Representative = 'representative';
    case Clinician = 'clinician';
    case Receptionist = 'receptionist';
    case Administrator = 'administrator';
    case Security = 'security';
    case Auditor = 'auditor';
    case DataProtectionOfficer = 'data_protection_officer';
    case Support = 'support';
    case System = 'system';
}
