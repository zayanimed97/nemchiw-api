<?php

namespace Modules\Organisers\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Organisers\Support\ApplicationStatus;
use Modules\Organisers\Support\DocumentType;

final class SubmitApplication
{
    public function __construct(private readonly SaveApplication $applications) {}

    public function __invoke(string $userId): OrganiserApplication
    {
        $application = $this->applications->editable($userId);
        $have = $application->exists ? $application->documents()->pluck('type')->all() : [];
        $missing = array_values(array_diff(DocumentType::ALL, $have));
        if (! $application->exists || $application->name === '' || $application->regions === [] || $missing !== []) {
            throw ValidationException::withMessages(['documents' => 'Add your name, regions and all four documents first']);
        }
        $application->forceFill(['status' => ApplicationStatus::SUBMITTED, 'submitted_at' => now()])->save();

        return $application;
    }
}
