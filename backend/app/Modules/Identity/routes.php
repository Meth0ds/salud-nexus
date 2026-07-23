<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\BeginTotpEnrollmentController;
use App\Modules\Identity\Http\Controllers\ConfirmTotpEnrollmentController;
use App\Modules\Identity\Http\Controllers\CurrentSessionController;
use App\Modules\Identity\Http\Controllers\DiscloseTotpQrCodeController;
use App\Modules\Identity\Http\Controllers\IssueCsrfCookieController;
use App\Modules\Identity\Http\Controllers\LogoutController;
use App\Modules\Identity\Http\Controllers\MfaStatusController;
use App\Modules\Identity\Http\Controllers\PasswordLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->prefix('api/v1/auth')
    ->name('api.v1.auth.')
    ->group(function (): void {
        Route::get('/csrf', IssueCsrfCookieController::class)
            ->middleware('throttle:api')
            ->name('csrf');

        Route::post('/login', PasswordLoginController::class)
            ->middleware('throttle:auth.login')
            ->name('login');

        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
            Route::get('/session', CurrentSessionController::class)->name('session');
            Route::post('/logout', LogoutController::class)->name('logout');
            Route::get('/mfa', MfaStatusController::class)->name('mfa.status');
            Route::post('/mfa/totp/enrollments', BeginTotpEnrollmentController::class)
                ->middleware('throttle:auth.mfa')
                ->name('mfa.totp.enrollments.store');
            Route::post(
                '/mfa/totp/enrollment-qr-disclosures',
                DiscloseTotpQrCodeController::class,
            )
                ->middleware('throttle:auth.mfa')
                ->name('mfa.totp.enrollment-qr-disclosures.store');
            Route::post(
                '/mfa/totp/enrollment-confirmations',
                ConfirmTotpEnrollmentController::class,
            )
                ->middleware('throttle:auth.mfa')
                ->name('mfa.totp.enrollment-confirmations.store');
        });
    });
