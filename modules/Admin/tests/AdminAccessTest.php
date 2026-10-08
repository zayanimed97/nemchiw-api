<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Models\Admin;
use Modules\Identity\Models\User;

it('creates an admin from the command only', function () {
    Artisan::call('admin:create', ['email' => 'me@example.com', '--name' => 'Me', '--password' => 'correct horse battery staple']);

    $admin = Admin::query()->where('email', 'me@example.com')->firstOrFail();
    expect(Hash::check('correct horse battery staple', $admin->password))->toBeTrue();
    $this->get('/admin/register')->assertNotFound();
});

it('sends guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('does not let app users in', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertRedirect('/admin/login');
});

it('asks an admin without two-factor to set it up', function () {
    $admin = Admin::factory()->create(['app_authentication_secret' => null]);

    $response = $this->actingAs($admin, 'admin')->get('/admin');

    expect($response->status())->toBe(302);
    expect($response->headers->get('Location'))->toContain('multi-factor');
});

it('lets an admin with two-factor in', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')->get('/admin')->assertOk();
});

it('serves the login page as HTML', function () {
    $this->get('/admin/login')->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
});

it('keeps API errors as JSON', function () {
    $this->get('/api/v1/nowhere')->assertNotFound()->assertJsonPath('code', 'not_found');
});
