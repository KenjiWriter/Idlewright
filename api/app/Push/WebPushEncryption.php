<?php

declare(strict_types=1);

namespace App\Push;

use InvalidArgumentException;
use RuntimeException;

/**
 * The encryption of the content of a push message (RFC 8291 over the content coding `aes128gcm` of RFC 8188), on `ext-openssl` and nothing else: only the browser of the driver
 * can read it, and the push service that carries it cannot. One record is enough, because a message is small.
 */
final class WebPushEncryption
{
    /** The size of a record that the header announces; the push services take a body of 4096 bytes. */
    public const RECORD_SIZE = 4096;

    /** The header: the salt (16), the size of a record (4), the length of the key id (1) and the key id, which is our public key (65). */
    public const HEADER_LENGTH = 86;

    /** What fits of the content: the body is 4096 bytes, and the header, the byte that ends the content and the tag of the cipher (16) are in it. */
    public const MAX_CONTENT = self::RECORD_SIZE - self::HEADER_LENGTH - 1 - 16;

    /**
     * The body of the request to the push service: the header and the encrypted content.
     *
     * @param  string  $content  what the service worker will read (the JSON of the notification)
     * @param  string  $browserPoint  the public key of the subscription (`p256dh`), 65 bytes
     * @param  string  $authSecret  the secret of the subscription (`auth`), 16 bytes
     * @param  array{private: string, public: string}|null  $ours  our key pair for this message; a new one by default (a test gives its own)
     * @param  string|null  $salt  16 random bytes; new ones by default (a test gives its own)
     */
    public static function encrypt(string $content, string $browserPoint, string $authSecret, ?array $ours = null, ?string $salt = null): string
    {
        if (strlen($content) > self::MAX_CONTENT) {
            throw new InvalidArgumentException('The content of a push message is at most '.self::MAX_CONTENT.' bytes.');
        }

        if (strlen($authSecret) !== 16) {
            throw new InvalidArgumentException('The secret of a subscription is 16 bytes.');
        }

        $ours ??= P256::generate();
        $salt ??= random_bytes(16);

        $shared = P256::agree($ours['private'], $ours['public'], $browserPoint);

        // The secret of the subscription is mixed with the shared one, and with both public keys so that the key belongs to this pair.
        $keyMaterial = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$browserPoint.$ours['public'], $authSecret);
        $key = hash_hkdf('sha256', $keyMaterial, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $keyMaterial, 12, "Content-Encoding: nonce\0", $salt);

        // 0x02 ends the last record: nothing is added after it, as the message is one record.
        $encrypted = openssl_encrypt($content."\x02", 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        if ($encrypted === false) {
            throw new RuntimeException('OpenSSL could not encrypt the message.');
        }

        return $salt.pack('N', self::RECORD_SIZE).chr(P256::POINT_LENGTH).$ours['public'].$encrypted.$tag;
    }
}
