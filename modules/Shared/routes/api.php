<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('_client-ip', function (Request $request) {
    abort_unless(config('shared.expose_client_ip'), 404);

    return ['ip' => $request->ip()];
});
