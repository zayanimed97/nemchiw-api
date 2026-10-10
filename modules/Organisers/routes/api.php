<?php

use Illuminate\Support\Facades\Route;
use Modules\Organisers\Http\ApplicationController;
use Modules\Organisers\Http\OrganiserController;
use Modules\Shared\Http\LimitBodySize;

Route::middleware('auth:sanctum')->prefix('me/organiser-application')->group(function () {
    Route::get('/', [ApplicationController::class, 'show']);
    Route::put('/', [ApplicationController::class, 'update']);
    Route::post('documents/{type}', [ApplicationController::class, 'document'])
        ->withoutMiddleware(LimitBodySize::class)
        ->middleware(LimitBodySize::class.':'.config('organisers.max_body_bytes'));
    Route::post('submit', [ApplicationController::class, 'submit']);
});

Route::get('organisers/{id}', OrganiserController::class)->where('id', '[0-9A-Za-z]{26}');
