<?php

use Illuminate\Support\Facades\Route;
use Modules\Trails\Http\TrailsController;

Route::get('trails', TrailsController::class)->middleware('cache.headers:public;max_age=300;etag');
