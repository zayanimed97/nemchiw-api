<?php

use Illuminate\Support\Facades\Event;
use Modules\Admin\Places\Moderate;
use Modules\Organisers\Actions\DecideApplication;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Shared\Events\ApplicationDecided;
use Modules\Shared\Events\ReportResolved;
use Modules\Shared\Places\PlaceModerated;
use Modules\Trails\Models\Trail;
use Modules\Trips\Actions\ResolveReport;
use Modules\Trips\Models\TripReport;
use Modules\Trips\Tests\Factories\TripRequestFactory;

it('announces organiser decisions', function () {
    Event::fake([ApplicationDecided::class]);
    $user = tripReady();
    $application = OrganiserApplication::query()->create([
        'user_id' => $user->id, 'name' => 'Rando Nord', 'regions' => ['jendouba'], 'experience_years' => 6,
        'public_phone' => '+21671000000', 'status' => 'submitted', 'submitted_at' => now(),
    ]);

    app(DecideApplication::class)->approve($application);
    app(DecideApplication::class)->revoke($application, 'Licence expired');

    Event::assertDispatched(ApplicationDecided::class, fn ($e) => $e->userId === $user->id && $e->outcome === 'approved');
    Event::assertDispatched(ApplicationDecided::class, fn ($e) => $e->userId === $user->id && $e->outcome === 'revoked');
});

it('names who added a moderated place', function () {
    Event::fake([PlaceModerated::class]);
    $author = tripReady();
    $trail = Trail::factory()->create(['id' => 'p', 'status' => 'pending', 'created_by' => $author->id]);

    app(Moderate::class)->approve('trail', 'p');

    Event::assertDispatched(PlaceModerated::class, fn ($e) => $e->createdBy === $author->id
        && $e->status === 'live' && $e->name === (array) $trail->name);
});

it('announces resolved reports to the reporter', function () {
    Event::fake([ReportResolved::class]);
    $request = TripRequestFactory::new()->accepted()->create();
    $report = TripReport::query()->create([
        'request_id' => $request->id, 'reporter_id' => $request->trip->organiser_id, 'reason' => 'unpaid', 'note' => 'No answer', 'status' => 'open',
    ]);

    app(ResolveReport::class)($report, 'closed_no_penalty', 'admin-1');

    Event::assertDispatched(ReportResolved::class, fn ($e) => $e->requestId === $request->id
        && $e->reporterId === $request->trip->organiser_id && $e->outcome === 'closed_no_penalty');
});
