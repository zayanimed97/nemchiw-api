<?php

use Illuminate\Support\Facades\Route;
use Modules\SocialAuth\Http\SocialSignInController;

Route::post('auth/social', SocialSignInController::class)->middleware('throttle:social');
