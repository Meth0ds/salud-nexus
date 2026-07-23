<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application;

use App\Modules\Documents\Domain\DocumentStatus;
use App\Modules\Documents\Infrastructure\Persistence\ClinicalDocument;
use App\Modules\Patients\Infrastructure\Persistence\Patient;
use Illuminate\Database\Eloquent\Collection;

/**
 * List the bounded set of documents currently published to a patient.
 */
final readonly class ListPatientDocuments
{
    /**
     * Return issued, owned documents ordered by their publication time.
     *
     * @return Collection<int, ClinicalDocument>
     */
    public function handle(Patient $patient): Collection
    {
        return ClinicalDocument::query()
            ->with(['activePublication.version', 'center'])
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->where('status', DocumentStatus::Issued->value)
            ->whereHas('activePublication')
            ->limit(100)
            ->get()
            ->sortByDesc(static fn (ClinicalDocument $document): int => $document
                ->activePublication
                ?->published_at
                ->getTimestamp() ?? 0)
            ->values();
    }
}
