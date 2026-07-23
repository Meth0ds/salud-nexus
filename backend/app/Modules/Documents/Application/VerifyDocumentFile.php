<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application;

use App\Modules\Documents\Infrastructure\Persistence\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Validate document storage boundaries, metadata, content type, size, and digest.
 */
final readonly class VerifyDocumentFile
{
    /**
     * Return the PDF bytes only after every stored integrity invariant passes.
     */
    public function handle(DocumentVersion $version): string
    {
        $diskName = config('documents.disk');
        $maximumBytes = config('documents.maximum_download_bytes');

        if (! is_string($diskName) || $diskName !== 'documents' || $version->storage_disk !== $diskName) {
            throw new RuntimeException('The document storage boundary is invalid.');
        }
        if (! is_int($maximumBytes) || $maximumBytes < 1 || $version->byte_size > $maximumBytes) {
            throw new RuntimeException('The document exceeds the authorized download boundary.');
        }
        if (
            $version->mime_type !== 'application/pdf'
            || $version->storage_path === ''
            || str_starts_with($version->storage_path, '/')
            || str_contains($version->storage_path, '\\')
            || in_array('..', explode('/', $version->storage_path), true)
        ) {
            throw new RuntimeException('The document file metadata is invalid.');
        }

        $disk = Storage::disk($diskName);
        if (! $disk->exists($version->storage_path)) {
            throw new RuntimeException('The document object is unavailable.');
        }

        $contents = $disk->get($version->storage_path);
        if (! is_string($contents)) {
            throw new RuntimeException('The document object could not be read.');
        }
        if (
            strlen($contents) !== $version->byte_size
            || ! hash_equals($version->sha256, hash('sha256', $contents))
            || ! str_starts_with($contents, '%PDF-')
        ) {
            throw new RuntimeException('The document integrity verification failed.');
        }

        return $contents;
    }
}
