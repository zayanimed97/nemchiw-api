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
    case Forbidden = 'forbidden';
    case NotTripReady = 'not_trip_ready';
    case WomenOnlyRequired = 'women_only_required';
    case NotCertified = 'not_certified';
    case AlreadyRequested = 'already_requested';
    case OwnTrip = 'own_trip';
    case TripLocked = 'trip_locked';
    case TripNotOpen = 'trip_not_open';
    case TripFull = 'trip_full';
    case NotAccepted = 'not_accepted';
    case ConversationClosed = 'conversation_closed';
    case MessageTooLong = 'message_too_long';
    case PhotoInvalid = 'photo_invalid';

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
            self::Forbidden, self::NotCertified, self::WomenOnlyRequired => 403,
            self::NotTripReady, self::OwnTrip, self::MessageTooLong, self::PhotoInvalid => 422,
            self::AlreadyRequested, self::TripLocked, self::TripNotOpen, self::TripFull, self::NotAccepted, self::ConversationClosed => 409,
        };
    }
}
