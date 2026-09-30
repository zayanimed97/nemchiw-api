<?php

use Illuminate\Support\Facades\Route;

Route::get('demo-ping', fn () => ['ok' => true]);
