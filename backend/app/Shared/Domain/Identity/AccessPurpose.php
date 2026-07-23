<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Enumerate the declared business purposes permitted in an actor context.
 */
enum AccessPurpose: string
{
    case PatientSelfService = 'patient_self_service';
    case CareDelivery = 'care_delivery';
    case CareOperations = 'care_operations';
    case Administration = 'administration';
    case Security = 'security';
    case Privacy = 'privacy';
    case Audit = 'audit';
    case Support = 'support';
    case SystemProcessing = 'system_processing';
}
