<?php

declare(strict_types=1);

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceApiRequestConstraints;
use App\Http\Middleware\RequireAuthenticationAssurance;
use App\Support\Http\ProblemDetailsFactory;
use App\Support\Logging\SanitizedExceptionReporter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(null);

        $middleware->trustHosts(
            at: static fn (): array => config('api.trusted_hosts', []),
            subdomains: false,
        );
        $middleware->alias([
            'auth.assurance' => RequireAuthenticationAssurance::class,
        ]);
        $middleware->append([
            AssignRequestId::class,
            ApplySecurityHeaders::class,
            EnforceApiRequestConstraints::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(
            static fn (Throwable $exception) => app(SanitizedExceptionReporter::class)->report($exception),
        )->stop();

        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request, Throwable $exception): bool => $request->is('api/*')
                || $request->expectsJson(),
        );

        $exceptions->render(
            static fn (Throwable $exception, Request $request) => $request->is('api/*')
                ? app(ProblemDetailsFactory::class)->fromThrowable($exception, $request)
                : null,
        );
    })->create();
