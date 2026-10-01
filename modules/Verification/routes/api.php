<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\Http\LimitBodySize;
use Modules\Verification\Http\DiditWebhookController;
use Modules\Verification\Http\StartVerificationController;

Route::post('me/verification', StartVerificationController::class)->middleware('auth:sanctum');

// Didit drops (never retries) a 4xx: let full decisions through; the HMAC check is cheap.
Route::post('webhooks/didit', DiditWebhookController::class)
    ->middleware('throttle:didit-webhook')
    ->withoutMiddleware(LimitBodySize::class)
    ->middleware(LimitBodySize::class.':524288');
