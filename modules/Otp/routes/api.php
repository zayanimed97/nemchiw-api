<?php

use Illuminate\Support\Facades\Route;
use Modules\Otp\Http\AttachPhoneController;
use Modules\Otp\Http\SignInOtpController;

Route::post('auth/otp/send', [SignInOtpController::class, 'send'])->middleware('throttle:otp-ip');
Route::post('auth/otp/verify', [SignInOtpController::class, 'verify'])->middleware('throttle:otp-verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('me/phone/otp/send', [AttachPhoneController::class, 'send'])->middleware(['throttle:otp-ip', 'throttle:otp-attach']);
    Route::post('me/phone/otp/verify', [AttachPhoneController::class, 'verify'])->middleware('throttle:otp-verify');
});
