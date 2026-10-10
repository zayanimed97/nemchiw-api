<?php

use Illuminate\Support\Facades\Route;
use Modules\Trips\Http\MyTripsController;
use Modules\Trips\Http\TripRequestsController;
use Modules\Trips\Http\TripsController;

$ulid = '[0-9A-Za-z]{26}'; // Laravel stores ULIDs lowercase

Route::get('trips', [TripsController::class, 'index']);
Route::get('trips/{id}', [TripsController::class, 'show'])->where('id', $ulid);

Route::middleware('auth:sanctum')->group(function () use ($ulid) {
    Route::post('trips', [TripsController::class, 'store']);
    Route::patch('trips/{id}', [TripsController::class, 'update'])->where('id', $ulid);
    Route::post('trips/{id}/requests', [TripRequestsController::class, 'store'])->where('id', $ulid);
    Route::post('trips/{id}/cancel', [TripRequestsController::class, 'cancelTrip'])->where('id', $ulid);
    Route::post('requests/{id}/{action}', [TripRequestsController::class, 'transition'])
        ->where(['id' => $ulid, 'action' => 'accept|decline|withdraw|cancel']);
    Route::get('me/trips', MyTripsController::class);
});
