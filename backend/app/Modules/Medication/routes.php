<?php

declare(strict_types=1);

use App\Modules\Medication\Http\Controllers\PatientMedicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:sanctum', 'throttle:api'])
    ->prefix('api/v1/patient/medications')
    ->name('api.v1.patient.medications.')
    ->group(function (): void {
        Route::get('/', [PatientMedicationController::class, 'index'])->name('index');
        Route::post('/declarations', [PatientMedicationController::class, 'declare'])
            ->middleware('throttle:patient.medication_mutation')
            ->name('declarations.store');
        Route::get('/{medication}', [PatientMedicationController::class, 'show'])
            ->where('medication', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-7[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}')
            ->name('show');
        Route::post('/{medication}/renewal-requests', [PatientMedicationController::class, 'renew'])
            ->where('medication', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-7[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}')
            ->middleware('throttle:patient.medication_mutation')
            ->name('renewal-requests.store');
    });
