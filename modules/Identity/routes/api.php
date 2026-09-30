<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\MeController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::delete('me', [MeController::class, 'destroy']);
    Route::post('auth/sign-out', [MeController::class, 'signOut']);
});
