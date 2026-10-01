<?php

use Illuminate\Support\Facades\Route;
use Modules\Verification\Http\StartVerificationController;

Route::post('me/verification', StartVerificationController::class)->middleware('auth:sanctum');
