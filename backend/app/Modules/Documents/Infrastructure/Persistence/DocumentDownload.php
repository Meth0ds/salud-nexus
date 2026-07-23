<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist an immutable audit record for a completed document download.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $identity_account_id
 * @property int $patient_id
 * @property int $document_id
 * @property int $document_version_id
 * @property int $document_download_grant_id
 * @property string $public_id
 * @property string $request_public_id
 * @property string $outcome
 * @property CarbonImmutable $downloaded_at
 */
#[Fillable([
    'organization_id',
    'identity_account_id',
    'patient_id',
    'document_id',
    'document_version_id',
    'document_download_grant_id',
    'public_id',
    'request_public_id',
    'outcome',
    'downloaded_at',
])]
#[Hidden([
    'id',
    'organization_id',
    'identity_account_id',
    'patient_id',
    'document_id',
    'document_version_id',
    'document_download_grant_id',
])]
final class DocumentDownload extends Model
{
    use HasPublicId;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['downloaded_at' => 'immutable_datetime'];
    }
}
