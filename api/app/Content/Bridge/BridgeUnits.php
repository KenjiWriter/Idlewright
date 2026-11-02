<?php

declare(strict_types=1);

namespace App\Content\Bridge;

use App\Content\Studio\Unit;

/** The names the block of changes and the instruction for the AI use for the units of the studio. */
final class BridgeUnits
{
    /** @var array<string, Unit> */
    public const UNITS = ['plain' => Unit::Plain, 'percent' => Unit::Percent, 'seconds' => Unit::Seconds, 'dollars' => Unit::Dollars, 'liters' => Unit::Liters];

    public static function token(Unit $unit): string
    {
        return (string) array_search($unit, self::UNITS, true);
    }
}
