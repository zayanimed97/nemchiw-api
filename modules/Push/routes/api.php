<?php

use Illuminate\Support\Facades\Route;
use Modules\Push\Http\PushTokenController;

Route::middleware('auth:sanctum')->group(function () {
    // POST twins: the host's firewall bans IPs that send PUT/DELETE (see Identity routes).
    Route::match(['post', 'put'], 'me/push-token', [PushTokenController::class, 'store']);
    Route::delete('me/push-token', [PushTokenController::class, 'destroy']);
    Route::post('me/push-token/delete', [PushTokenController::class, 'destroy']);
});
