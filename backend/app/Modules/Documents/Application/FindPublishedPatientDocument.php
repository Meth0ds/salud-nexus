<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application;

use App\Modules\Documents\Domain\DocumentStatus;
use App\Modules\Documents\Infrastructure\Persistence\ClinicalDocument;
use App\Modules\Patients\Infrastructure\Persistence\Patient;

/**
 * Resolve one issued document through mandatory patient ownership scopes.
 */
final readonly class FindPublishedPatientDocument
{
    /**
     * Return a published owned document or an indistinguishable not-found result.
     */
    public function handle(Patient $patient, string $publicId): ClinicalDocument
    {
        return ClinicalDocument::query()
            ->with(['activePublication.version', 'center'])
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('public_id', strtolower($publicId))
            ->where('status', DocumentStatus::Issued->value)
            ->whereHas('activePublication')
            ->firstOrFail();
    }
}
