<?php

namespace Modules\Organisers\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organisers\Contracts\OrganiserRecords;
use Modules\Organisers\Models\OrganiserApplication;
use Modules\Organisers\Support\DocumentType;

/** @mixin OrganiserApplication */
final class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $have = $this->documents->pluck('type')->all();

        return [
            'status' => $this->status,
            'name' => $this->name,
            'regions' => $this->regions,
            'experienceYears' => $this->experience_years,
            'publicPhone' => $this->public_phone,
            'rejectionReason' => $this->rejection_reason,
            'documents' => collect(DocumentType::ALL)->mapWithKeys(fn ($type) => [$type => in_array($type, $have, true)])->all(),
            'certified' => app(OrganiserRecords::class)->isCertified($this->user_id),
        ];
    }
}
