<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Application\AuditChainVerifier;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Infrastructure\Integrity\AuditIntegrity;
use App\Modules\Audit\Infrastructure\Persistence\DatabaseAuditChainVerifier;
use App\Modules\Audit\Infrastructure\Persistence\DatabaseAuditWriter;
use Illuminate\Support\ServiceProvider;
use LogicException;

/**
 * Register audit adapters and require a strong production integrity key.
 */
final class AuditServiceProvider extends ServiceProvider
{
    /**
     * Register audit integrity, writer, and verifier services.
     */
    public function register(): void
    {
        $this->app->singleton(
            AuditIntegrity::class,
            static fn (): AuditIntegrity => new AuditIntegrity(config('audit.integrity_key')),
        );
        $this->app->bind(AuditWriter::class, DatabaseAuditWriter::class);
        $this->app->bind(AuditChainVerifier::class, DatabaseAuditChainVerifier::class);
    }

    /**
     * Fail production startup when audit-chain signing is unavailable.
     */
    public function boot(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        try {
            $this->app->make(AuditIntegrity::class);
        } catch (LogicException $exception) {
            throw new LogicException('A strong audit integrity key is required in production.', previous: $exception);
        }
    }
}
