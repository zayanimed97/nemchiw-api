<?php

namespace Modules\SocialAuth\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\Accounts;
use Modules\SocialAuth\Models\SocialIdentity;
use Modules\SocialAuth\Providers\AppleVerifier;
use Modules\SocialAuth\Providers\FacebookVerifier;
use Modules\SocialAuth\Providers\GoogleVerifier;
use Modules\SocialAuth\Providers\ProviderIdentity;
use Modules\SocialAuth\Providers\ProviderVerifier;

final class SignInWithProvider
{
    private const NAME_MAX = 40;

    public function __construct(private readonly Accounts $accounts) {}

    /**
     * @param  array{firstName?: ?string, lastName?: ?string, email?: ?string}  $hint
     * @return array{token: string, profile: array<string, mixed>, isNew: bool}
     */
    public function __invoke(string $provider, string $token, ?string $nonce, array $hint): array
    {
        $identity = $this->verifier($provider)->verify($token, $nonce);

        $existing = $this->owner($identity);

        return $existing !== null
            ? $this->accounts->authResponse($existing, false)
            : $this->firstSignIn($identity, $hint);
    }

    /**
     * Creates the account and links the identity in one transaction. If a parallel first
     * sign-in linked the same identity in between, ours is rolled back and theirs is used.
     *
     * @param  array{firstName?: ?string, lastName?: ?string, email?: ?string}  $hint
     * @return array{token: string, profile: array<string, mixed>, isNew: bool}
     */
    public function firstSignIn(ProviderIdentity $identity, array $hint): array
    {
        try {
            $userId = DB::transaction(function () use ($identity, $hint) {
                $userId = $this->accounts->createAccount(
                    self::name($identity->firstName ?? $hint['firstName'] ?? null),
                    self::name($identity->lastName ?? $hint['lastName'] ?? null),
                    self::email($identity->email ?? $hint['email'] ?? null),
                );
                (new SocialIdentity)->forceFill([
                    'user_id' => $userId,
                    'provider' => $identity->provider,
                    'provider_user_id' => $identity->subject,
                ])->save();

                return $userId;
            });
        } catch (UniqueConstraintViolationException $e) {
            $winner = $this->owner($identity) ?? throw $e;

            return $this->accounts->authResponse($winner, false);
        }

        return $this->accounts->authResponse($userId, true);
    }

    private function owner(ProviderIdentity $identity): ?string
    {
        return SocialIdentity::query()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->subject)
            ->value('user_id');
    }

    private function verifier(string $provider): ProviderVerifier
    {
        return app(match ($provider) {
            'google' => GoogleVerifier::class,
            'apple' => AppleVerifier::class,
            'facebook' => FacebookVerifier::class,
        });
    }

    /** Untrusted text: no control or invisible/bidi characters, trimmed, at most 40 characters. */
    private static function name(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = trim((string) preg_replace('/\p{C}+/u', '', $value));

        return $clean === '' ? null : mb_substr($clean, 0, self::NAME_MAX);
    }

    private static function email(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value !== null && strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }
}
