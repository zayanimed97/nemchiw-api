<?php

namespace Modules\Organisers\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Organisers\Models\OrganiserDocument;
use Modules\Organisers\Support\ApplicationStatus;

/** Stores one document on the private disk, replacing the previous one of that type. */
final class StoreDocument
{
    public function __construct(private readonly SaveApplication $applications) {}

    public function __invoke(string $userId, string $type, UploadedFile $file): OrganiserApplication
    {
        $application = $this->applications->editable($userId);
        if (! $application->exists) {
            $application->save();
        }
        $disk = Storage::disk(config('organisers.documents_disk'));
        $path = $file->storeAs("organiser-documents/{$application->id}", $type.'-'.bin2hex(random_bytes(8)).'.'.$file->extension(), ['disk' => config('organisers.documents_disk')]);

        $previous = $application->documents()->where('type', $type)->first();
        if ($previous !== null) {
            $disk->delete($previous->path);
            $previous->delete();
        }
        OrganiserDocument::query()->create([
            'application_id' => $application->id, 'type' => $type, 'path' => $path,
            'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(),
        ]);
        if ($application->status === ApplicationStatus::REJECTED) {
            $application->forceFill(['status' => ApplicationStatus::DRAFT, 'rejection_reason' => null])->save();
        }

        return $application->refresh();
    }
}
