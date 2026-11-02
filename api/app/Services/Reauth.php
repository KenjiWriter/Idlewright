<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A recent proof of who the driver is, for what cannot be taken back (unlinking a sign-in). It is given when the driver opens
 * a link sent to the e-mail of the account, or signs in again with a provider the account has, and it belongs to the
 * device that asked: the same account on another device does not have it. It lasts `ride.account.reauth_ttl_seconds`.
 *
 * It is kept in the cache. Losing it only means that the driver has to prove it again.
 */
final class Reauth
{
    public static function ttlSeconds(): int
    {
        return (int) config('ride.account.reauth_ttl_seconds');
    }

    /** The device the request comes from: a token of the account. Null for anything else, which can prove nothing. */
    public static function deviceOf(User $user): ?int
    {
        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? (int) $token->getKey() : null;
    }

    /** Gives the proof to a device. */
    public function grant(int $deviceTokenId): void
    {
        Cache::put(self::key($deviceTokenId), now()->getTimestamp(), self::ttlSeconds());
    }

    /** Seconds the proof of the device of this request still lasts; 0 when it has none. */
    public function remaining(User $user): int
    {
        $device = self::deviceOf($user);
        $at = $device === null ? null : Cache::get(self::key($device));

        return is_int($at) ? max(0, $at + self::ttlSeconds() - now()->getTimestamp()) : 0;
    }

    public function isFresh(User $user): bool
    {
        return $this->remaining($user) > 0;
    }

    private static function key(int $deviceTokenId): string
    {
        return "reauth:{$deviceTokenId}";
    }
}
