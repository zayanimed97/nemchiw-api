<?php

use Illuminate\Support\Facades\Route;
use Modules\Push\Http\PushTokenController;

Route::middleware('auth:sanctum')->group(function () {
    Route::put('me/push-token', [PushTokenController::class, 'store']);
    Route::delete('me/push-token', [PushTokenController::class, 'destroy']);
});
