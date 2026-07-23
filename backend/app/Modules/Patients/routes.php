<?php

declare(strict_types=1);

use App\Modules\Patients\Http\Controllers\BookingOptionsController;
use App\Modules\Patients\Http\Controllers\PatientAppointmentsController;
use App\Modules\Patients\Http\Controllers\PatientDashboardController;
use App\Modules\Patients\Http\Controllers\PatientProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:sanctum', 'throttle:api'])
    ->prefix('api/v1/patient')
    ->name('api.v1.patient.')
    ->group(function (): void {
        Route::get('/profile', PatientProfileController::class)->name('profile');
        Route::get('/dashboard', PatientDashboardController::class)->name('dashboard');
        Route::get('/booking-options', BookingOptionsController::class)->name('booking-options');
        Route::get('/appointments', [PatientAppointmentsController::class, 'index'])
            ->name('appointments.index');
        Route::post(
            '/appointments/{appointment}/cancellations',
            [PatientAppointmentsController::class, 'cancel'],
        )
            ->where('appointment', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-7[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}')
            ->middleware('throttle:patient.appointment_change')
            ->name('appointments.cancellations.store');
        Route::post(
            '/appointments/{appointment}/reschedules',
            [PatientAppointmentsController::class, 'reschedule'],
        )
            ->where('appointment', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-7[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}')
            ->middleware('throttle:patient.appointment_change')
            ->name('appointments.reschedules.store');
        Route::get('/appointments/{appointment}', [PatientAppointmentsController::class, 'show'])
            ->where('appointment', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-7[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}')
            ->name('appointments.show');
        Route::post('/appointments', [PatientAppointmentsController::class, 'store'])
            ->middleware('throttle:patient.booking')
            ->name('appointments.store');
    });
