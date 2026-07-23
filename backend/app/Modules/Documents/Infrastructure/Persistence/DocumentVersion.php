<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Persist immutable storage metadata and integrity evidence for one file version.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $document_id
 * @property string $public_id
 * @property int $version_number
 * @property string $mime_type
 * @property int $byte_size
 * @property string $sha256
 * @property string $storage_disk
 * @property string $storage_path
 * @property CarbonImmutable $issued_at
 */
#[Fillable([
    'organization_id',
    'document_id',
    'public_id',
    'version_number',
    'mime_type',
    'byte_size',
    'sha256',
    'storage_disk',
    'storage_path',
    'issued_at',
])]
#[Hidden(['id', 'organization_id', 'document_id', 'sha256', 'storage_disk', 'storage_path'])]
final class DocumentVersion extends Model
{
    use HasPublicId;

    /**
     * Get the document aggregate that owns this version.
     *
     * @return BelongsTo<ClinicalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ClinicalDocument::class, 'document_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'issued_at' => 'immutable_datetime',
            'version_number' => 'integer',
        ];
    }

    /**
     * Register guards that keep issued document versions immutable.
     */
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Issued document versions are immutable.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Issued document versions cannot be deleted.');
        });
    }
}
