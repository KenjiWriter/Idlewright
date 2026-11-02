<?php

declare(strict_types=1);

namespace App\Push;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * The curve P-256 on `ext-openssl`, in the raw forms that Web Push uses: a private key is its 32 bytes and a public key is the 65 bytes of the uncompressed point
 * (`04 || x || y`). OpenSSL takes keys as PEM, so a raw key is wrapped in the DER that says what it is.
 */
final class P256
{
    public const PRIVATE_LENGTH = 32;

    public const POINT_LENGTH = 65;

    /** SubjectPublicKeyInfo of an EC key on prime256v1, up to the point. */
    private const PUBLIC_HEAD = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** ECPrivateKey (RFC 5915): the version, the 32 bytes of the key, then the curve and the point. */
    private const PRIVATE_HEAD = '30770201010420';

    private const PRIVATE_MIDDLE = 'a00a06082a8648ce3d030107a144034200';

    /** @return array{private: string, public: string} a new key pair, as the raw bytes */
    public static function generate(): array
    {
        $key = openssl_pkey_new(['config' => self::config(), 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = $key === false ? null : (openssl_pkey_get_details($key)['ec'] ?? null);
        self::drain();

        if ($ec === null) {
            throw new RuntimeException('OpenSSL could not make a key on the curve P-256.');
        }

        return ['private' => self::pad($ec['d']), 'public' => "\x04".self::pad($ec['x']).self::pad($ec['y'])];
    }

    /** The private key of the raw bytes `$private`, whose public key is `$point`. */
    public static function privateKey(string $private, string $point): OpenSSLAsymmetricKey
    {
        if (strlen($private) !== self::PRIVATE_LENGTH) {
            throw new InvalidArgumentException('A private key on P-256 is 32 bytes.');
        }

        self::checkPoint($point);

        $key = openssl_pkey_get_private(self::pem('EC PRIVATE KEY', hex2bin(self::PRIVATE_HEAD).$private.hex2bin(self::PRIVATE_MIDDLE).$point));
        self::drain();

        return $key === false ? throw new InvalidArgumentException('This is not a private key on P-256.') : $key;
    }

    /** The public key of a point; a point that is not on the curve is refused. */
    public static function publicKey(string $point): OpenSSLAsymmetricKey
    {
        self::checkPoint($point);

        $key = openssl_pkey_get_public(self::pem('PUBLIC KEY', hex2bin(self::PUBLIC_HEAD).$point));
        self::drain();

        return $key === false ? throw new InvalidArgumentException('This point is not on the curve P-256.') : $key;
    }

    /** Whether the 65 bytes are a public key on the curve. */
    public static function isPoint(string $point): bool
    {
        try {
            self::publicKey($point);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** The shared secret (ECDH) of our private key and the public key of the other side. */
    public static function agree(string $private, string $ownPoint, string $peerPoint): string
    {
        $secret = openssl_pkey_derive(self::publicKey($peerPoint), self::privateKey($private, $ownPoint), 32);
        self::drain();

        return $secret === false ? throw new RuntimeException('OpenSSL could not agree on a secret.') : $secret;
    }

    private static function checkPoint(string $point): void
    {
        if (strlen($point) !== self::POINT_LENGTH || $point[0] !== "\x04") {
            throw new InvalidArgumentException('A public key on P-256 is 65 bytes that start with 04.');
        }
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$label}-----\n";
    }

    /** OpenSSL drops the zeros at the front of a number; a coordinate or a key is always 32 bytes. */
    private static function pad(string $number): string
    {
        return str_pad($number, self::PRIVATE_LENGTH, "\0", STR_PAD_LEFT);
    }

    /** The smallest configuration that OpenSSL needs to make a key: PHP on Windows has none of its own, and on a server this one does no harm. */
    private static function config(): string
    {
        return dirname(__DIR__, 2).'/resources/openssl.cnf';
    }

    /** OpenSSL keeps the messages of what went wrong; they are read here, so the next call does not find them. */
    private static function drain(): void
    {
        while (openssl_error_string() !== false) {
            // Nothing: the queue is emptied.
        }
    }
}
