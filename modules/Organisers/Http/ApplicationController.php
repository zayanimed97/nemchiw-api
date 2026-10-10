<?php

namespace Modules\Organisers\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organisers\Actions\SaveApplication;
use Modules\Organisers\Actions\StoreDocument;
use Modules\Organisers\Actions\SubmitApplication;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Organisers\Support\DocumentType;

final class ApplicationController
{
    public function show(Request $request, OrganiserRecords $records): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $application = OrganiserApplication::query()->where('user_id', $userId)->first();

        return new JsonResponse($application === null
            ? ['status' => 'none', 'certified' => $records->isCertified($userId)]
            : ApplicationResource::make($application)->resolve($request));
    }

    public function update(ApplicationFieldsRequest $request, SaveApplication $save): JsonResponse
    {
        $application = $save((string) $request->user()->getAuthIdentifier(), $request->validated());

        return new JsonResponse(ApplicationResource::make($application)->resolve($request));
    }

    public function document(Request $request, string $type, StoreDocument $store): JsonResponse
    {
        abort_unless(in_array($type, DocumentType::ALL, true), 404);
        $request->validate(['file' => ['required', 'file', 'mimes:'.DocumentType::MIMES, 'max:'.DocumentType::MAX_KB]]);
        $application = $store((string) $request->user()->getAuthIdentifier(), $type, $request->file('file'));

        return new JsonResponse(ApplicationResource::make($application)->resolve($request));
    }

    public function submit(Request $request, SubmitApplication $submit): JsonResponse
    {
        $application = $submit((string) $request->user()->getAuthIdentifier());

        return new JsonResponse(ApplicationResource::make($application)->resolve($request));
    }
}
