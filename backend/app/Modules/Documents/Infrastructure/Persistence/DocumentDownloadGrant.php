<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persist a hashed, short-lived, one-time document download authorization.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $identity_account_id
 * @property int $patient_id
 * @property int $document_id
 * @property int $document_version_id
 * @property string $public_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property-read ClinicalDocument $document
 * @property-read DocumentVersion $version
 */
#[Fillable([
    'organization_id',
    'identity_account_id',
    'patient_id',
    'document_id',
    'document_version_id',
    'public_id',
    'token_hash',
    'expires_at',
    'consumed_at',
])]
#[Hidden([
    'id',
    'organization_id',
    'identity_account_id',
    'patient_id',
    'document_id',
    'document_version_id',
    'token_hash',
])]
final class DocumentDownloadGrant extends Model
{
    use HasPublicId;

    /**
     * Get the document authorized by the grant.
     *
     * @return BelongsTo<ClinicalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ClinicalDocument::class, 'document_id');
    }

    /**
     * Get the exact immutable version authorized by the grant.
     *
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }
}
