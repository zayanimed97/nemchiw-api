<?php

namespace Modules\Organisers\Actions;

use Illuminate\Support\Facades\Storage;
use Modules\Organisers\Models\OrganiserDocument;
use Modules\Organisers\Support\ApplicationStatus;

/** Documents of refused or revoked applications are deleted after the grace period. Hourly. */
final class PurgeDecidedDocuments
{
    public function __invoke(): void
    {
        $disk = Storage::disk(config('organisers.documents_disk'));
        OrganiserDocument::query()
            ->whereHas('application', fn ($q) => $q
                ->whereIn('status', [ApplicationStatus::REJECTED, ApplicationStatus::REVOKED])
                ->where('decided_at', '<', now()->subDays((int) config('organisers.purge_after_days'))))
            ->each(function (OrganiserDocument $document) use ($disk) {
                $disk->delete($document->path);
                $document->delete();
            });
    }
}
