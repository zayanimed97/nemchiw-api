<?php

namespace Modules\Shared\Errors;

use RuntimeException;

/** An expected failure the app knows how to show. Not reported to the log. */
final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly ApiErrorCode $error,
        string $message,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
