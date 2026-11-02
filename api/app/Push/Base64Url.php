<?php

declare(strict_types=1);

namespace App\Push;

/** The URL-safe base64 of RFC 4648 §5 with no padding, which is how the keys of a push subscription and of VAPID travel. */
final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** The bytes of a text, or null when it is not base64url (a browser sends it with no padding; one that is padded is fine too). */
    public static function decode(string $text): ?string
    {
        $text = rtrim($text, '=');

        if ($text === '' || preg_match('/\A[A-Za-z0-9_-]+\z/', $text) !== 1) {
            return null;
        }

        $bytes = base64_decode(strtr($text, '-_', '+/').str_repeat('=', (4 - strlen($text) % 4) % 4), true);

        return $bytes === false ? null : $bytes;
    }
}
