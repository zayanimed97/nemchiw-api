<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\ReviewQueue;
use Modules\Admin\Models\Admin;
use Modules\Centres\Models\Centre;
use Modules\Trails\Models\Trail;
use Modules\Trips\Tests\Factories\TripFactory;

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

it('shows the trip waiting for a submitted place', function () {
    Trail::factory()->create(['id' => 'ras-el-ain', 'status' => 'pending']);
    TripFactory::new()->create([
        'status' => 'draft', 'title' => 'Sunday loop', 'destination_kind' => 'trail', 'destination_id' => 'ras-el-ain',
    ]);

    $pending = Livewire::test(ReviewQueue::class)->instance()->getPending();

    expect($pending[0]['waitingTrip'])->toBe('Sunday loop');
});
