<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\MeController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    // POST twins: the host's firewall bans IPs that send PATCH/PUT/DELETE or a method-override
    // header, so the app only ever sends GET and POST.
    Route::match(['post', 'patch'], 'me', [MeController::class, 'update']);
    Route::delete('me', [MeController::class, 'destroy']);
    Route::post('me/delete', [MeController::class, 'destroy']);
    Route::post('auth/sign-out', [MeController::class, 'signOut']);
});
