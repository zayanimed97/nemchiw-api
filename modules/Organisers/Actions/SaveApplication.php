<?php

namespace Modules\Organisers\Actions;

use Modules\Identity\Contracts\People;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Organisers\Support\ApplicationStatus;
use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

/** Creates or edits the person's application. Editing a refused one makes it a draft again. */
final class SaveApplication
{
    public function __construct(private readonly People $people) {}

    /** @param array{name: string, regions: list<string>, experienceYears: int, publicPhone?: ?string} $fields */
    public function __invoke(string $userId, array $fields): OrganiserApplication
    {
        $application = $this->editable($userId);
        $application->fill([
            'name' => $fields['name'],
            'regions' => array_values($fields['regions']),
            'experience_years' => $fields['experienceYears'],
            'public_phone' => $fields['publicPhone'] ?? null,
            'status' => ApplicationStatus::DRAFT,
            'rejection_reason' => null,
        ])->save();

        return $application;
    }

    /** The application the person may change now, created on first use. */
    public function editable(string $userId): OrganiserApplication
    {
        if (! $this->people->tripReadiness($userId)['ready']) {
            throw new ApiException(ApiErrorCode::NotTripReady, 'Finish your trip-ready profile first');
        }
        $application = OrganiserApplication::query()->firstOrNew(['user_id' => $userId], [
            'name' => '', 'regions' => [], 'experience_years' => 0, 'status' => ApplicationStatus::DRAFT,
        ]);
        if ($application->exists && ! in_array($application->status, ApplicationStatus::EDITABLE, true)) {
            throw new ApiException(ApiErrorCode::AlreadyRequested, 'This application is already sent');
        }

        return $application;
    }
}
