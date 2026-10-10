<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Modules\Admin\Filament\Resources\Trips\Pages\ListTrips;
use Modules\Admin\Models\Admin;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Trips\Tests\Factories\TripFactory;

beforeEach(fn () => $this->actingAs(Admin::factory()->create(), 'admin'));

it('lists trips and takes one down without counting against the organiser', function () {
    $trip = TripFactory::new()->create();

    Livewire::test(ListTrips::class)
        ->assertCanSeeTableRecords([$trip])
        ->callAction(TestAction::make('takeDown')->table($trip));

    expect($trip->fresh()->status)->toBe('cancelled');
    expect(app(OrganiserRecords::class)->cards([$trip->organiser_id])[$trip->organiser_id]['record']['cancelledTrips'])->toBe(0);
});
