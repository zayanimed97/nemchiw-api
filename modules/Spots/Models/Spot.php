<?php

namespace Modules\Spots\Models;

use Illuminate\Database\Eloquent\Model;

final class Spot extends Model
{
    public const TYPES = ['beach', 'forest', 'desert', 'mountain'];

    public const ACCESS = ['car', 'fourByFour'];

    public const COVERAGE = ['none', 'weak', 'good'];

    public const PERMIT = ['yes', 'no', 'unknown'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'name', 'description', 'latitude', 'longitude', 'governorate',
        'type', 'access', 'water', 'coverage', 'permit',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'water' => 'boolean',
        ];
    }
}
