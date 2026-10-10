<?php

namespace Modules\Trips\Actions;

use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripReport;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Models\TripRequestMember;
use Modules\Trips\Support\ReportStatus;

final class FileReport
{
    public const REASONS = ['unpaid', 'no_show_risk', 'behaviour', 'other'];

    public function __invoke(TripRequest $request, string $reporterId, string $reason, ?string $note): TripReport
    {
        $organiserId = Trip::query()->whereKey($request->trip_id)->value('organiser_id');
        $isMember = TripRequestMember::query()->where('request_id', $request->id)->where('user_id', $reporterId)->exists();
        if ($reporterId !== $organiserId && ! $isMember) {
            throw new ApiException(ApiErrorCode::Forbidden, 'Only the organiser or the camper can report this');
        }
        $open = TripReport::query()->where('request_id', $request->id)->where('reporter_id', $reporterId)
            ->where('status', ReportStatus::OPEN)->exists();
        if ($open) {
            throw new ApiException(ApiErrorCode::AlreadyRequested, 'You already reported this. We are looking at it');
        }

        return TripReport::query()->create([
            'request_id' => $request->id, 'reporter_id' => $reporterId, 'reason' => $reason,
            'note' => $note, 'status' => ReportStatus::OPEN,
        ]);
    }
}
