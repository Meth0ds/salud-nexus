<?php

declare(strict_types=1);

use App\Modules\Documents\Http\Controllers\PatientDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:sanctum', 'throttle:api'])
    ->prefix('api/v1/patient')
    ->name('api.v1.patient.documents.')
    ->group(function (): void {
        Route::get('/documents', [PatientDocumentController::class, 'index'])->name('index');
        Route::post('/documents/{document}/download-authorizations', [PatientDocumentController::class, 'authorizeDownload'])
            ->where('document', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-7[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}')
            ->middleware('throttle:patient.document_download')
            ->name('download-authorizations.store');
        Route::get('/document-downloads/{token}', [PatientDocumentController::class, 'download'])
            ->where('token', '[A-Za-z0-9_-]{43}')
            ->middleware('throttle:patient.document_download')
            ->name('downloads.show');
    });
