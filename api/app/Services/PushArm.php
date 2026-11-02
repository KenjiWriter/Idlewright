<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;

/**
 * The group of the experiment that an account is in: told by push (`push`) or never told and never asked (`holdout`, the group that the others are compared with, to see that the
 * notifications do not make drivers come back less). The group is a function of the number of the account, so nothing is written and the account stays in it; `push.holdout_ppm`
 * says how big the group is.
 */
final class PushArm
{
    public const PUSH = 'push';

    public const HOLDOUT = 'holdout';

    public function __construct(private readonly Rules $rules) {}

    public function of(int $userId): string
    {
        $ppm = $this->rules->push()['holdout_ppm'] ?? 0;
        $bucket = hexdec(substr(hash('sha256', config('ride.push.arm_salt').'|'.$userId), 0, 8)) % 1_000_000;

        return $bucket < $ppm ? self::HOLDOUT : self::PUSH;
    }

    public function isHoldout(int $userId): bool
    {
        return $this->of($userId) === self::HOLDOUT;
    }
}
