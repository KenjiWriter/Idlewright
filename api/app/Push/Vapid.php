<?php

declare(strict_types=1);

namespace App\Push;

use InvalidArgumentException;
use RuntimeException;

/**
 * VAPID (RFC 8292): the way a game tells a push service who it is. The game has a key pair of its own, and each request carries a short-lived token (a JWT signed with ES256)
 * that names the service the request goes to, so that a token taken from one request is of no use at another service. The public key goes to the browser when it subscribes.
 */
final class Vapid
{
    /** How long a token lives; the standard allows a day at most. */
    public const TOKEN_SECONDS = 43_200;

    /** @return array{public: string, private: string} a new key pair of a game, as base64url */
    public static function generate(): array
    {
        $pair = P256::generate();

        return ['public' => Base64Url::encode($pair['public']), 'private' => Base64Url::encode($pair['private'])];
    }

    /** The value of the `Authorization` header of a request to `$endpoint`: `vapid t=<token>, k=<public key>`. */
    public static function authorization(string $endpoint, string $subject, string $public, string $private, int $now): string
    {
        return 'vapid t='.self::token(self::audience($endpoint), $subject, $now + self::TOKEN_SECONDS, $public, $private).', k='.$public;
    }

    /** The token: who it is for (`aud`, the origin of the push service), when it ends (`exp`) and who to write to (`sub`). */
    public static function token(string $audience, string $subject, int $expires, string $public, string $private): string
    {
        $head = Base64Url::encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
        $claims = Base64Url::encode(json_encode(['aud' => $audience, 'exp' => $expires, 'sub' => $subject], JSON_THROW_ON_ERROR));

        $point = Base64Url::decode($public);
        $secret = Base64Url::decode($private);

        if ($point === null || $secret === null) {
            throw new InvalidArgumentException('The keys of VAPID are base64url.');
        }

        if (! openssl_sign("{$head}.{$claims}", $der, P256::privateKey($secret, $point), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('OpenSSL could not sign the token.');
        }

        return "{$head}.{$claims}.".Base64Url::encode(self::signature($der));
    }

    /** The origin of an address: the scheme, the host and a port that is not the usual one. */
    public static function audience(string $endpoint): string
    {
        $parts = parse_url($endpoint);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('This is not an address of a push service.');
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** OpenSSL signs as DER (two integers in a sequence); a JWT wants the two numbers as 32 bytes each, one after the other. */
    private static function signature(string $der): string
    {
        // 30 <len> 02 <len r> r 02 <len s> s, and the length of the sequence is one byte (it is 70 to 72 for this curve).
        $offset = 2;
        $parts = [];

        for ($i = 0; $i < 2; $i++) {
            if (($der[$offset] ?? '') !== "\x02") {
                throw new RuntimeException('The signature is not what OpenSSL gives for ECDSA.');
            }

            $length = ord($der[$offset + 1]);
            $parts[] = substr($der, $offset + 2, $length);
            $offset += 2 + $length;
        }

        return implode('', array_map(static fn (string $number): string => str_pad(ltrim($number, "\0"), 32, "\0", STR_PAD_LEFT), $parts));
    }
}
