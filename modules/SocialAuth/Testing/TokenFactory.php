<?php

namespace Modules\SocialAuth\Testing;

use Firebase\JWT\JWT;
use OpenSSLAsymmetricKey;

/** Signs ID tokens with throwaway RSA keys and serves the matching JWKS, for tests. */
final class TokenFactory
{
    /** @var array<string, OpenSSLAsymmetricKey> */
    private static array $keys = [];

    /** @return array{keys: list<array<string, string>>} */
    public static function jwks(string $kid = 'test-key'): array
    {
        $rsa = openssl_pkey_get_details(self::key($kid))['rsa'];

        return ['keys' => [[
            'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $kid,
            'n' => self::b64($rsa['n']), 'e' => self::b64($rsa['e']),
        ]]];
    }

    /** @param  array<string, mixed>  $claims */
    public static function sign(array $claims, string $kid = 'test-key'): string
    {
        openssl_pkey_export(self::key($kid), $private);

        return JWT::encode($claims, $private, 'RS256', $kid);
    }

    public static function publicPem(string $kid = 'test-key'): string
    {
        return openssl_pkey_get_details(self::key($kid))['key'];
    }

    private static function key(string $kid): OpenSSLAsymmetricKey
    {
        return self::$keys[$kid] ??= openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
