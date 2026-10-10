<?php

namespace Modules\Organisers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrganiserApplication extends Model
{
    use HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['user_id', 'name', 'regions', 'experience_years', 'public_phone', 'status', 'rejection_reason', 'submitted_at', 'decided_at'];

    protected function casts(): array
    {
        return ['regions' => 'array', 'experience_years' => 'integer', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    /** @return HasMany<OrganiserDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(OrganiserDocument::class, 'application_id');
    }
}
