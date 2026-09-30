<?php

use Illuminate\Support\Facades\Route;
use Modules\Otp\Http\SignInOtpController;

Route::post('auth/otp/send', [SignInOtpController::class, 'send'])->middleware('throttle:otp-ip');
Route::post('auth/otp/verify', [SignInOtpController::class, 'verify'])->middleware('throttle:otp-verify');
