<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Admin\Filament\Resources\OrganiserApplications\Pages\ListOrganiserApplications;
use Modules\Admin\Models\Admin;
use Modules\Admin\Models\AdminAction;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Organisers\Models\OrganiserDocument;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = Admin::factory()->create();
    $this->actingAs($this->admin, 'admin');
});

function submittedApplication(): OrganiserApplication
{
    $application = OrganiserApplication::query()->create([
        'user_id' => tripReady()->id, 'name' => 'Rando Nord', 'regions' => ['jendouba'], 'experience_years' => 6,
        'public_phone' => '+21671000000', 'status' => 'submitted', 'submitted_at' => now(),
    ]);
    Storage::disk('local')->put("organiser-documents/{$application->id}/licence.pdf", '%PDF-1.4 test');
    OrganiserDocument::query()->create(['application_id' => $application->id, 'type' => 'licence',
        'path' => "organiser-documents/{$application->id}/licence.pdf", 'mime' => 'application/pdf', 'size' => 13]);

    return $application;
}

it('lists submitted applications and approves one', function () {
    $application = submittedApplication();

    Livewire::test(ListOrganiserApplications::class)
        ->assertCanSeeTableRecords([$application])
        ->callAction(TestAction::make('approve')->table($application));

    expect($application->fresh()->status)->toBe('approved');
    expect(app(OrganiserRecords::class)->isCertified($application->user_id))->toBeTrue();
    expect(AdminAction::query()->where('action', 'approved')->where('target_id', $application->id)->exists())->toBeTrue();
});

it('needs a reason to reject or revoke', function () {
    $application = submittedApplication();

    Livewire::test(ListOrganiserApplications::class)
        ->callAction(TestAction::make('reject')->table($application), data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);
    Livewire::test(ListOrganiserApplications::class)
        ->callAction(TestAction::make('reject')->table($application), data: ['reason' => 'Licence unreadable']);

    expect($application->fresh())->status->toBe('rejected')->rejection_reason->toBe('Licence unreadable');
});

it('streams a document to an admin and logs the view', function () {
    $document = submittedApplication()->documents()->sole();

    $this->get("/admin/documents/{$document->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(AdminAction::query()->where('action', 'viewed')->where('target_kind', 'org_document')->where('target_id', $document->id)->exists())->toBeTrue();
});

it('keeps documents from anyone who is not an admin', function () {
    $document = submittedApplication()->documents()->sole();
    $this->app['auth']->forgetGuards(); // a guest (Admin has no remember_token, so no logout())

    $this->get("/admin/documents/{$document->id}")->assertRedirect();
    $this->withToken(tokenFor(tripReady()))->get("/admin/documents/{$document->id}")->assertRedirect();
});
