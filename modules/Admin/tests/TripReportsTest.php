<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Modules\Admin\Filament\Resources\TripReports\Pages\ListTripReports;
use Modules\Admin\Models\Admin;
use Modules\Trips\Models\TripReport;
use Modules\Trips\Tests\Factories\TripRequestFactory;

beforeEach(fn () => $this->actingAs(Admin::factory()->create(), 'admin'));

it('shows open reports and closes one with no penalty', function () {
    $request = TripRequestFactory::new()->accepted()->create();
    $report = TripReport::query()->create(['request_id' => $request->id, 'reporter_id' => $request->trip->organiser_id,
        'reason' => 'unpaid', 'note' => 'No answer', 'status' => 'open']);

    Livewire::test(ListTripReports::class)
        ->assertCanSeeTableRecords([$report])
        ->callAction(TestAction::make('closeNoPenalty')->table($report));

    expect($report->fresh()->status)->toBe('closed_no_penalty');
    expect($request->fresh()->status)->toBe('closed');
});
