<?php

namespace Modules\Trips\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Trips\Actions\FileReport;
use Modules\Trips\Models\TripRequest;

final class ReportsController
{
    public function __invoke(Request $request, string $id, FileReport $file): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(FileReport::REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $report = $file(TripRequest::query()->findOrFail($id), (string) $request->user()->getAuthIdentifier(), $data['reason'], $data['note'] ?? null);

        return new JsonResponse(['id' => $report->id, 'status' => $report->status], 201);
    }
}
