<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence;

use App\Modules\Identity\Infrastructure\Persistence\IdentityAccount;
use Database\Factories\PatientPortalLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bind one identity account to one patient within the organization boundary.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $patient_id
 * @property int $identity_account_id
 * @property-read Patient $patient
 * @property-read IdentityAccount $identityAccount
 */
#[Fillable(['organization_id', 'patient_id', 'identity_account_id'])]
#[Hidden(['id', 'organization_id', 'patient_id', 'identity_account_id'])]
final class PatientPortalLink extends Model
{
    /**
     * Enable model factories for portal-link fixtures.
     *
     * @use HasFactory<PatientPortalLinkFactory>
     */
    use HasFactory;

    /**
     * Get the patient represented by the portal identity.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the identity account permitted to use the patient portal.
     *
     * @return BelongsTo<IdentityAccount, $this>
     */
    public function identityAccount(): BelongsTo
    {
        return $this->belongsTo(IdentityAccount::class);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PatientPortalLinkFactory
    {
        return PatientPortalLinkFactory::new();
    }
}
