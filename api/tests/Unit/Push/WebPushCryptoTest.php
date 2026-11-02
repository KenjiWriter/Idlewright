<?php

declare(strict_types=1);

use App\Push\Base64Url;
use App\Push\P256;
use App\Push\Vapid;
use App\Push\WebPushEncryption;

// The encryption of a push message and the token of VAPID, checked against the example of RFC 8291 (byte for byte) and against a receiver that reads what was sent.

/** The example of section 5 of RFC 8291: what the application server sends to the push service, written as the RFC does (the lines are joined). */
const RFC8291_BODY = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27ml'
    .'mlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPT'
    .'pK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

/** A signature of a token (the 32 bytes of r and the 32 of s) as the DER that OpenSSL verifies. */
function signatureAsDer(string $raw): string
{
    $integer = static function (string $number): string {
        $number = ltrim($number, "\0");
        $number = ($number === '' || ord($number[0]) > 127) ? "\0".$number : $number;

        return "\x02".chr(strlen($number)).$number;
    };
    $body = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32));

    return "\x30".chr(strlen($body)).$body;
}

describe('the encryption of the content', function () {
    it('gives the body of the example of RFC 8291, byte for byte', function () {
        $body = WebPushEncryption::encrypt(
            'When I grow up, I want to be a watermelon',
            Base64Url::decode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
            Base64Url::decode('BTBZMqHH6r4Tts7J_aSIgg'),
            ['private' => Base64Url::decode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'), 'public' => Base64Url::decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8')],
            Base64Url::decode('DGv6ra1nlYgDCS1FRnbzlw'),
        );

        // The RFC says "Content-Length: 145" above the example, but the body it shows is 144 bytes: 86 of header, 41 of text, the byte that ends it and 16 of tag.
        expect(Base64Url::encode($body))->toBe(RFC8291_BODY)->and(strlen($body))->toBe(144);
    });

    it('is read by the browser that it was made for, with a new key and a new salt every time', function () {
        $browser = P256::generate();
        $auth = random_bytes(16);
        $content = json_encode(['title' => 'Rdzak GT-4 stoi', 'body' => 'Skończyło się paliwo.', 'url' => '/garage'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $first = WebPushEncryption::encrypt($content, $browser['public'], $auth);
        $second = WebPushEncryption::encrypt($content, $browser['public'], $auth);

        expect(receivePush($first, $browser['private'], $browser['public'], $auth))->toBe($content)
            ->and(receivePush($second, $browser['private'], $browser['public'], $auth))->toBe($content)
            ->and($first)->not->toBe($second)
            ->and(strlen($first))->toBe(WebPushEncryption::HEADER_LENGTH + strlen($content) + 1 + 16);
    });

    it('cannot be read with another secret of the subscription', function () {
        $browser = P256::generate();
        $body = WebPushEncryption::encrypt('hello', $browser['public'], str_repeat('a', 16));

        expect(receivePush($body, $browser['private'], $browser['public'], str_repeat('b', 16)))->toBeNull();
    });

    it('takes a message that fills the body of 4096 bytes and no more', function () {
        $browser = P256::generate();
        $full = str_repeat('x', WebPushEncryption::MAX_CONTENT);

        expect(strlen(WebPushEncryption::encrypt($full, $browser['public'], str_repeat('a', 16))))->toBe(4096)
            ->and(fn () => WebPushEncryption::encrypt($full.'x', $browser['public'], str_repeat('a', 16)))->toThrow(InvalidArgumentException::class);
    });

    it('refuses a subscription whose key is not a point of the curve or whose secret has the wrong length', function () {
        $invalidPoint = "\x04".str_repeat("\x01", 64);

        expect(P256::isPoint($invalidPoint))->toBeFalse()
            ->and(P256::isPoint(P256::generate()['public']))->toBeTrue()
            ->and(P256::isPoint('short'))->toBeFalse()
            ->and(fn () => WebPushEncryption::encrypt('hello', $invalidPoint, str_repeat('a', 16)))->toThrow(InvalidArgumentException::class)
            ->and(fn () => WebPushEncryption::encrypt('hello', P256::generate()['public'], 'short'))->toThrow(InvalidArgumentException::class);
    });
});

describe('the token of VAPID', function () {
    it('is signed by the key of the game, for the push service that the request goes to, and ends in a time', function () {
        $keys = Vapid::generate();
        $header = Vapid::authorization('https://fcm.googleapis.com/fcm/send/abc', 'mailto:kontakt@example.test', $keys['public'], $keys['private'], 1_800_000_000);

        expect($header)->toStartWith('vapid t=')->and($header)->toEndWith(', k='.$keys['public']);

        preg_match('/^vapid t=([\w-]+)\.([\w-]+)\.([\w-]+), k=/', $header, $found);
        [, $head, $claims, $signature] = $found;

        expect(json_decode(Base64Url::decode($head), true))->toBe(['typ' => 'JWT', 'alg' => 'ES256'])
            ->and(json_decode(Base64Url::decode($claims), true))->toBe(['aud' => 'https://fcm.googleapis.com', 'exp' => 1_800_000_000 + Vapid::TOKEN_SECONDS, 'sub' => 'mailto:kontakt@example.test']);

        $raw = Base64Url::decode($signature);

        expect(strlen($raw))->toBe(64)
            ->and(openssl_verify("{$head}.{$claims}", signatureAsDer($raw), P256::publicKey(Base64Url::decode($keys['public'])), OPENSSL_ALGO_SHA256))->toBe(1)
            ->and(openssl_verify("{$head}.{$claims}x", signatureAsDer($raw), P256::publicKey(Base64Url::decode($keys['public'])), OPENSSL_ALGO_SHA256))->toBe(0);
    });

    it('names the origin of the service, with a port that is not the usual one', function () {
        expect(Vapid::audience('https://updates.push.services.mozilla.com/wpush/v2/abc?x=1'))->toBe('https://updates.push.services.mozilla.com')
            ->and(Vapid::audience('https://push.example.test:8443/a'))->toBe('https://push.example.test:8443')
            ->and(fn () => Vapid::audience('not an address'))->toThrow(InvalidArgumentException::class);
    });

    it('makes a different pair of keys every time, as keys that browsers take (65 bytes of a point and 32 of a secret)', function () {
        $first = Vapid::generate();
        $second = Vapid::generate();

        expect($first['public'])->not->toBe($second['public'])
            ->and(strlen(Base64Url::decode($first['public'])))->toBe(65)
            ->and(strlen(Base64Url::decode($first['private'])))->toBe(32);
    });
});

describe('base64url', function () {
    it('takes what a browser sends, with or without padding, and nothing else', function () {
        expect(Base64Url::decode('YWJj'))->toBe('abc')->and(Base64Url::decode('YQ'))->toBe('a')->and(Base64Url::decode('YQ=='))->toBe('a')
            ->and(Base64Url::decode('a+b/'))->toBeNull()->and(Base64Url::decode(''))->toBeNull()->and(Base64Url::decode('Y Q'))->toBeNull()
            ->and(Base64Url::encode("\xfb\xff"))->toBe('-_8');
    });
});
