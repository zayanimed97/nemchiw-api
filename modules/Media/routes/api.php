<?php

use Illuminate\Support\Facades\Route;
use Modules\Media\Http\PhotoController;
use Modules\Shared\Http\LimitBodySize;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('me/photo', [PhotoController::class, 'store'])
        ->withoutMiddleware(LimitBodySize::class)
        ->middleware(LimitBodySize::class.':'.config('media.max_body_bytes'));
    Route::get('photos/{photo}', [PhotoController::class, 'show'])->where('photo', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}');
});
