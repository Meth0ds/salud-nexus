<?php

declare(strict_types=1);

return [
    'disk' => env('DOCUMENTS_DISK', 'documents'),
    'download_grant_ttl_seconds' => (int) env('DOCUMENT_DOWNLOAD_GRANT_TTL_SECONDS', 90),
    'maximum_download_bytes' => (int) env('DOCUMENT_MAXIMUM_DOWNLOAD_BYTES', 10_485_760),
];
