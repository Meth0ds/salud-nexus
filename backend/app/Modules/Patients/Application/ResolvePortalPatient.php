<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use App\Modules\Organizations\Domain\OrganizationStatus;
use App\Modules\Patients\Domain\PatientStatus;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use App\Modules\Patients\Infrastructure\Persistence\PatientPortalLink;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Resolve the active patient represented by an authenticated portal identity.
 */
final readonly class ResolvePortalPatient
{
    /**
     * Resolve the immutable one-to-one portal link and its mandatory tenant.
     *
     * @throws AuthorizationException
     */
    public function handle(IdentityAccount $account): Patient
    {
        $link = PatientPortalLink::query()
            ->where('identity_account_id', $account->id)
            ->whereHas('patient', static function ($query): void {
                $query->where('status', PatientStatus::Active->value)
                    ->whereHas('organization', static function ($organizationQuery): void {
                        $organizationQuery->where('status', OrganizationStatus::Active->value);
                    });
            })
            ->with(['patient.organization', 'patient.homeCenter'])
            ->first();

        if (! $link instanceof PatientPortalLink) {
            throw new AuthorizationException;
        }

        $patient = $link->patient;

        if ($patient->organization_id !== $link->organization_id) {
            throw new AuthorizationException;
        }

        return $patient;
    }
}
