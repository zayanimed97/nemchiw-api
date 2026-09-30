<?php

namespace Modules\Shared\Errors;

/**
 * Server-side ApiErrorCode values from src/api/types.ts. `network` and
 * `provider_cancelled` are produced by the app itself, never by the server.
 */
enum ApiErrorCode: string
{
    case Validation = 'validation';
    case InvalidCode = 'invalid_code';
    case CodeExpired = 'code_expired';
    case RateLimited = 'rate_limited';
    case Unauthenticated = 'unauthenticated';
    case NotFound = 'not_found';
    case PhoneTaken = 'phone_taken';
    case ProviderUnavailable = 'provider_unavailable';
    case Server = 'server';

    public function status(): int
    {
        return match ($this) {
            self::Validation, self::InvalidCode, self::CodeExpired => 422,
            self::RateLimited => 429,
            self::Unauthenticated => 401,
            self::NotFound => 404,
            self::PhoneTaken => 409,
            self::ProviderUnavailable => 503,
            self::Server => 500,
        };
    }
}
