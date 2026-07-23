<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ApiIndexController;
use App\Http\Controllers\Api\V1\Health\LivenessController;
use App\Http\Controllers\Api\V1\Health\ReadinessController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

Route::middleware('throttle:api')->group(function (): void {
    Route::get('/', ApiIndexController::class)->name('api.v1.index');
});

Route::prefix('health')->middleware('throttle:health')->group(function (): void {
    Route::get('/live', LivenessController::class)->name('api.v1.health.live');
    Route::get('/ready', ReadinessController::class)->name('api.v1.health.ready');
});

Route::fallback(static function (): never {
    throw new NotFoundHttpException;
});
