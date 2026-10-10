<?php

namespace Modules\Trips\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripReport;
use Modules\Trips\Models\TripRequest;
use Modules\Trips\Support\ReportStatus;
use Modules\Trips\Support\RequestStatus;

/** The admin's decision on a report (Filament, Task 9). */
final class ResolveReport
{
    public function __construct(private readonly RequestTransitions $transitions, private readonly OrganiserRecords $records) {}

    public function __invoke(TripReport $report, string $outcome, string $adminId): void
    {
        if ($report->status !== ReportStatus::OPEN || ! in_array($outcome, ReportStatus::OUTCOMES, true)) {
            throw new \InvalidArgumentException("Cannot resolve a {$report->status} report as {$outcome}");
        }
        DB::transaction(function () use ($report, $outcome, $adminId) {
            $request = TripRequest::query()->findOrFail($report->request_id);
            Trip::query()->lockForUpdate()->findOrFail($request->trip_id);
            $request->refresh();
            $open = in_array($request->status, RequestStatus::OPEN, true);
            if ($outcome === ReportStatus::CLOSED_NO_PENALTY && $open) {
                $this->transitions->end($request, RequestStatus::CLOSED, 'admin');
            }
            if ($outcome === ReportStatus::CLOSED_ORGANISER) {
                if ($open) {
                    $this->transitions->end($request, RequestStatus::CANCELLED, 'admin');
                }
                $this->records->bump(Trip::query()->whereKey($request->trip_id)->value('organiser_id'), 'cancelled_campers');
            }
            $report->forceFill(['status' => $outcome, 'resolved_at' => now(), 'resolved_by' => $adminId])->save();
        });
    }
}
