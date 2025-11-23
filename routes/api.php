<?php

use App\Http\Controllers\Api\IngestionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('webhooks')->group(function () {
    // Each bank should have its own dedicated endpoint
    Route::post('/foodics-bank', [WebhookController::class, 'handleBank'])->defaults('bank', 'foodics_bank');
    Route::post('/acme-bank', [WebhookController::class, 'handleBank'])->defaults('bank', 'acme_bank');
});



Route::prefix('payments')->group(function () {
    Route::post('/transfer', [PaymentController::class, 'transfer']);

    Route::prefix('ingestion')->group(function () {
        // Ingestion control (should be protected in production with auth middleware)
        Route::post('/pause', [IngestionController::class, 'pauseIngestion']);
        Route::post('/resume', [IngestionController::class, 'resumeIngestion']);
        Route::get('/status', [IngestionController::class, 'ingestionStatus']);
    });
});

