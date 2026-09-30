<?php

use Illuminate\Support\Facades\Route;
use Modules\Spots\Http\SpotsController;

Route::get('spots', SpotsController::class)->middleware('cache.headers:public;max_age=300;etag');
