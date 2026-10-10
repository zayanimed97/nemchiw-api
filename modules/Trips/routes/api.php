<?php

use Illuminate\Support\Facades\Route;
use Modules\Trips\Http\TripsController;

$ulid = '[0-9A-Za-z]{26}'; // Laravel stores ULIDs lowercase

Route::get('trips', [TripsController::class, 'index']);
Route::get('trips/{id}', [TripsController::class, 'show'])->where('id', $ulid);

Route::middleware('auth:sanctum')->group(function () use ($ulid) {
    Route::post('trips', [TripsController::class, 'store']);
    Route::patch('trips/{id}', [TripsController::class, 'update'])->where('id', $ulid);
});
