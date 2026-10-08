<?php

use Illuminate\Support\Facades\Route;
use Modules\Centres\Http\CentresController;

Route::get('centres', CentresController::class)->middleware('cache.headers:public;max_age=300;etag');
