<?php

namespace Modules\Admin\Http;

use Illuminate\Support\Facades\Storage;
use Modules\Admin\Models\AdminAction;
use Modules\Organisers\Models\OrganiserDocument;
use Symfony\Component\HttpFoundation\Response;

/** One organiser document, inline, for an admin. Every view is logged. */
final class OrganiserDocumentController
{
    public function __invoke(string $document): Response
    {
        $model = OrganiserDocument::query()->findOrFail($document);
        $disk = Storage::disk(config('organisers.documents_disk'));
        abort_unless($disk->exists($model->path), 404);
        AdminAction::query()->create([
            'admin_id' => auth('admin')->id(), 'action' => 'viewed',
            'target_kind' => 'org_document', 'target_id' => $model->id, 'changes' => null,
        ]);

        return response($disk->get($model->path), 200, [
            'Content-Type' => $model->mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
