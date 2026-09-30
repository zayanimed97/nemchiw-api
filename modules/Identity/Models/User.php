<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Factories\UserFactory;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** Phone is deliberately absent: it is only ever set after OTP proof. */
    protected $fillable = [
        'first_name', 'last_name', 'gender', 'birth_date', 'email', 'level', 'skills',
        'bio', 'home_governorate', 'home_city', 'car_seats', 'emergency_contact',
    ];

    /** Nothing private leaves the model by accident (toArray/json). */
    protected $hidden = [
        'phone', 'phone_verified_at', 'email', 'gender', 'birth_date', 'last_name', 'emergency_contact',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'phone_verified_at' => 'datetime',
            'skills' => 'array',
            'car_seats' => 'integer',
            'emergency_contact' => 'encrypted:array',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
