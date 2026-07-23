<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Report unexpected failures without serializing messages, traces, or request data.
 */
final class SanitizedExceptionReporter
{
    /**
     * Emit a correlation-friendly, non-sensitive exception fingerprint.
     */
    public function report(Throwable $exception): void
    {
        Log::error('Unhandled application exception.', [
            'exception_class' => $exception::class,
            'fingerprint' => hash(
                'sha256',
                $exception::class.'|'.$exception->getFile().'|'.$exception->getLine(),
            ),
            'request_id' => Context::get('request_id'),
        ]);
    }
}
