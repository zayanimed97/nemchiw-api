<?php

namespace Modules\Identity\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum writes last_used_at on every request. We write it at most every
 * 15 minutes, and each write pushes the expiry 90 days out (sliding expiry).
 */
final class PersonalAccessToken extends SanctumToken
{
    public function save(array $options = []): bool
    {
        if ($this->exists && array_keys($this->getDirty()) === ['last_used_at']) {
            $previous = $this->getOriginal('last_used_at');
            if ($previous !== null && $previous->greaterThan(now()->subMinutes(15))) {
                $this->syncOriginal();

                return true;
            }
            $this->expires_at = now()->addDays((int) config('identity.token_days'));
        }

        return parent::save($options);
    }
}
