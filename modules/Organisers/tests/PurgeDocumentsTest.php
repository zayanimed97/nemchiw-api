<?php

use Illuminate\Support\Facades\Storage;
use Modules\Organisers\Actions\PurgeDecidedDocuments;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Organisers\Models\OrganiserDocument;

it('deletes documents 30 days after a refusal or revocation, keeps approved ones', function () {
    Storage::fake('local');
    $make = function (string $status, int $daysAgo) {
        $application = OrganiserApplication::query()->create([
            'user_id' => tripReady()->id, 'name' => 'X', 'regions' => ['tunis'], 'experience_years' => 1,
            'status' => $status, 'decided_at' => now()->subDays($daysAgo),
        ]);
        $path = "organiser-documents/{$application->id}/licence.jpg";
        Storage::disk('local')->put($path, 'x');
        OrganiserDocument::query()->create(['application_id' => $application->id, 'type' => 'licence', 'path' => $path, 'mime' => 'image/jpeg', 'size' => 1]);

        return $path;
    };
    $oldRejected = $make('rejected', 31);
    $recentRejected = $make('rejected', 29);
    $oldRevoked = $make('revoked', 40);
    $approved = $make('approved', 400);

    app(PurgeDecidedDocuments::class)();

    Storage::disk('local')->assertMissing($oldRejected);
    Storage::disk('local')->assertMissing($oldRevoked);
    Storage::disk('local')->assertExists($recentRejected);
    Storage::disk('local')->assertExists($approved);
    expect(OrganiserDocument::query()->count())->toBe(2);
});
