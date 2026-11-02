<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * What an extra of the simulator does on a day: the cash it spends or brings, and the factor it puts on the income of the car, and what it does to the experience
 * of the day (a factor of the experience that the driving gave, and experience that it brings). The two for the experience are 1 and 0 for an extra that leaves it alone,
 * so the extras that were made before them play as they did.
 */
final readonly class DayEffect
{
    public function __construct(
        /** Dollars: negative when the extra spends them, positive when it brings them (what a find was sold for). */
        public float $cash = 0.0,
        /** 1 changes nothing, 1.2 makes the car earn a fifth more today. */
        public float $incomeFactor = 1.0,
        /** 1 changes nothing, 1.2 gives a fifth more of the experience that the driving of today gave. */
        public float $xpFactor = 1.0,
        /** Experience that the extra brings on top of what the driving gave (a reward that pays it out). */
        public float $xp = 0.0,
    ) {}

    public static function none(): self
    {
        return new self;
    }
}
