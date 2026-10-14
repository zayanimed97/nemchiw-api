<?php

namespace Modules\Push\Services;

/**
 * One notification for one person, before it is written in their phone's language.
 * `title` and `body` are keys in lang/{locale}/push.php; `rawBody` is a person's own words.
 * Param values may be localized arrays (place names): the sender picks the locale.
 */
final class Notice
{
    /**
     * @param  array<string, string|array<string, string>>  $params
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $title,
        public readonly array $params = [],
        public readonly ?string $body = null,
        public readonly ?string $rawBody = null,
        public readonly array $data = [],
        public readonly ?string $thread = null,
    ) {}
}
