<?php

use Illuminate\Support\Facades\Route;
use Modules\Verification\Http\DiditWebhookController;
use Modules\Verification\Http\StartVerificationController;

Route::post('me/verification', StartVerificationController::class)->middleware('auth:sanctum');

Route::post('webhooks/didit', DiditWebhookController::class)->middleware('throttle:didit-webhook');
