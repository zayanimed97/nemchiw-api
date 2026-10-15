<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\Http\LimitBodySize;
use Modules\Trips\Http\MyTripsController;
use Modules\Trips\Http\PlacesController;
use Modules\Trips\Http\ReportsController;
use Modules\Trips\Http\TripRequestsController;
use Modules\Trips\Http\TripsController;

$ulid = '[0-9A-Za-z]{26}'; // Laravel stores ULIDs lowercase

Route::get('trips', [TripsController::class, 'index']);
Route::get('trips/{id}', [TripsController::class, 'show'])->where('id', $ulid);

Route::middleware('auth:sanctum')->group(function () use ($ulid) {
    Route::post('trips', [TripsController::class, 'store']);
    // POST twin: the host's firewall bans IPs that send PATCH (see Identity routes).
    Route::match(['post', 'patch'], 'trips/{id}', [TripsController::class, 'update'])->where('id', $ulid);
    Route::post('trips/{id}/requests', [TripRequestsController::class, 'store'])->where('id', $ulid);
    Route::post('trips/{id}/cancel', [TripRequestsController::class, 'cancelTrip'])->where('id', $ulid);
    Route::post('requests/{id}/scan', [TripRequestsController::class, 'scan'])->where('id', $ulid);
    Route::post('requests/{id}/reports', ReportsController::class)->where('id', $ulid);
    Route::post('requests/{id}/{action}', [TripRequestsController::class, 'transition'])
        ->where(['id' => $ulid, 'action' => 'accept|decline|withdraw|cancel']);
    Route::get('me/trips', MyTripsController::class);
    Route::post('places/{kind}', [PlacesController::class, 'store'])->where('kind', 'spot|trail')->middleware('throttle:place-submissions');
    Route::post('places/{kind}/{id}/photos', [PlacesController::class, 'photo'])
        ->where(['kind' => 'spot|trail', 'id' => '[a-z0-9-]{1,64}'])
        ->withoutMiddleware(LimitBodySize::class)
        ->middleware([LimitBodySize::class.':'.config('media.max_body_bytes'), 'throttle:photo-uploads']);
});
