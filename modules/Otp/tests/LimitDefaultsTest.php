<?php

use Illuminate\Support\Facades\Route;

/*
 * Tunisian carriers put many subscribers behind one public IP (carrier-grade NAT).
 * Per-IP limits are a coarse backstop; per-phone, failure and global limits do the
 * real work. These floors keep a busy mobile egress from locking people out.
 */
it('leaves room for many people behind one carrier IP', function () {
    expect(config('otp.limits.per_ip_per_hour'))->toBeGreaterThanOrEqual(60);
    expect(config('otp.limits.verify_per_ip_per_10_min'))->toBeGreaterThanOrEqual(60);
});

it('lets a shared IP make 300 api requests a minute', function () {
    Route::prefix('api/v1/test')->middleware('api')->post('echo', fn () => ['ok' => true]);

    foreach (range(1, 300) as $_) {
        $this->postJson('/api/v1/test/echo')->assertOk();
    }
});
