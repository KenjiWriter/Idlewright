<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * When a driver last looked at the game, to the millisecond. `users.last_seen_at` is written once in ten minutes, which is too coarse to tell a driver who is playing from one
 * who has just left, so every request writes the moment here, to the cache (which does not keep it long: a driver who has not been seen for a day is not "seen").
 */
final class PushSeen
{
    private const SECONDS = 86_400;

    public static function touch(int $userId, int $nowMs): void
    {
        Cache::put(self::key($userId), $nowMs, self::SECONDS);
    }

    /** The moment of the last look, or null when there is none to know of (the cache was cleared; then the coarse one of the account is all there is). */
    public static function at(User $user): ?int
    {
        $cached = Cache::get(self::key($user->id));

        return is_int($cached) ? $cached : $user->last_seen_at?->getTimestampMs();
    }

    private static function key(int $userId): string
    {
        return "push-seen:{$userId}";
    }
}
