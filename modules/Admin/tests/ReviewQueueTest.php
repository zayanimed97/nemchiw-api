<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\ReviewQueue;
use Modules\Admin\Models\Admin;
use Modules\Centres\Models\Centre;
use Modules\Trails\Models\Trail;

beforeEach(fn () => $this->actingAs(Admin::factory()->create(), 'admin'));

it('lists pending places of every kind, oldest first', function () {
    Centre::factory()->create(['id' => 'newer', 'status' => 'pending', 'created_at' => now()]);
    Trail::factory()->create(['id' => 'older', 'status' => 'pending', 'created_at' => now()->subDay()]);
    Trail::factory()->create(['id' => 'live-one']);

    $pending = Livewire::test(ReviewQueue::class)->instance()->getPending();

    expect(array_column($pending, 'id'))->toBe(['older', 'newer']);
});

it('approves a place', function () {
    Trail::factory()->create(['id' => 'p', 'status' => 'pending']);

    Livewire::test(ReviewQueue::class)->callAction(TestAction::make('approve')->arguments(['kind' => 'trail', 'id' => 'p']));

    expect(Trail::findOrFail('p')->status)->toBe('live');
});

it('needs a reason to reject', function () {
    Trail::factory()->create(['id' => 'p', 'status' => 'pending']);

    Livewire::test(ReviewQueue::class)
        ->callAction(TestAction::make('reject')->arguments(['kind' => 'trail', 'id' => 'p']), data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);
    expect(Trail::findOrFail('p')->status)->toBe('pending');

    Livewire::test(ReviewQueue::class)
        ->callAction(TestAction::make('reject')->arguments(['kind' => 'trail', 'id' => 'p']), data: ['reason' => 'Duplicate of an existing trail']);
    expect(Trail::findOrFail('p'))->status->toBe('rejected')->rejection_reason->toBe('Duplicate of an existing trail');
});
