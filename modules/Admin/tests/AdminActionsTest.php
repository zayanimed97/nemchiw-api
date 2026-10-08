<?php

use Modules\Admin\Models\Admin;
use Modules\Admin\Models\AdminAction;
use Modules\Admin\Places\Moderate;
use Modules\Trails\Models\Trail;

it('records who changed what', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $trail = Trail::factory()->create(['id' => 't', 'status' => 'pending']);
    app(Moderate::class)->approve('trail', 't');
    $trail->refresh()->delete();

    $actions = AdminAction::query()->orderBy('id')->get();
    expect($actions->pluck('action')->all())->toBe(['created', 'updated', 'deleted']);
    expect($actions->every(fn ($a) => $a->admin_id === $admin->id && $a->target_kind === 'trail' && $a->target_id === 't'))->toBeTrue();
    expect($actions[1]->changes)->toHaveKey('status');
});

it('records nothing for changes made outside the panel', function () {
    Trail::factory()->create();

    expect(AdminAction::query()->count())->toBe(0);
});
