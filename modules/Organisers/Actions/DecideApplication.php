<?php

namespace Modules\Organisers\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Organisers\Models\Organiser;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Organisers\Support\ApplicationStatus;
use Modules\Shared\Events\ApplicationDecided;

/** The admin's decisions (Filament, Task 9). */
final class DecideApplication
{
    public function approve(OrganiserApplication $application): void
    {
        DB::transaction(function () use ($application) {
            $application->forceFill(['status' => ApplicationStatus::APPROVED, 'decided_at' => now(), 'rejection_reason' => null])->save();
            $organiser = Organiser::query()->firstOrCreate(['user_id' => $application->user_id]);
            $organiser->forceFill(['certified_at' => now(), 'public_phone' => $application->public_phone])->save();
            event(new ApplicationDecided($application->user_id, 'approved'));
        });
    }

    public function reject(OrganiserApplication $application, string $reason): void
    {
        $application->forceFill(['status' => ApplicationStatus::REJECTED, 'decided_at' => now(), 'rejection_reason' => $reason])->save();
        event(new ApplicationDecided($application->user_id, 'rejected'));
    }

    public function revoke(OrganiserApplication $application, string $reason): void
    {
        DB::transaction(function () use ($application, $reason) {
            $application->forceFill(['status' => ApplicationStatus::REVOKED, 'decided_at' => now(), 'rejection_reason' => $reason])->save();
            Organiser::query()->whereKey($application->user_id)->update(['certified_at' => null]);
            event(new ApplicationDecided($application->user_id, 'revoked'));
        });
    }
}
