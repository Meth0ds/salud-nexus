<?php

declare(strict_types=1);

return [
    /*
    | Base64-encoded random key of at least 32 bytes. Keep it outside the database
    | so a database-only compromise cannot silently rebuild a valid chain.
    */
    'integrity_key' => env('AUDIT_INTEGRITY_KEY'),
];
