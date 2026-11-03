<?php

declare(strict_types=1);

namespace App\Domain\Gigs;

use App\Domain\Settlement\Ppm;

/**
 * The chances of a night run, from the numbers of the document alone, so that the game (`GigPolice`), the report of the pay and the economy simulator all work out the same:
 * the patrols stop a part of the night runs, the body class of the car and the heat of an escape change that part, and a stop ends in an escape or in an arrest.
 */
final class NightOdds
{
    /**
     * @param  array<string, mixed>  $night  the night run of the document of the gigs
     * @return array{patrol_ppm: int, class_ppm: int, heat_ppm: int, control_ppm: int, escape_share_ppm: int, clean_ppm: int, escaped_ppm: int, arrested_ppm: int, noise: string}
     */
    public static function of(array $night, string $class, bool $heated): array
    {
        $patrol = $night['risk']['control_ppm'];
        $byClass = 0;

        foreach ($night['risk']['modifiers'] as $modifier) {
            if (in_array($class, $modifier['class_in'], true)) {
                $byClass += $modifier['delta_ppm'];
            }
        }

        $heat = $heated ? $night['risk']['heat_ppm'] : 0;
        $control = max(0, min(Ppm::ONE, $patrol + $byClass + $heat));
        $escaped = Ppm::mul($control, $night['risk']['escape_ppm']);

        return [
            'patrol_ppm' => $patrol,
            'class_ppm' => $byClass,
            'heat_ppm' => $heat,
            'control_ppm' => $control,
            'escape_share_ppm' => $night['risk']['escape_ppm'],
            'clean_ppm' => Ppm::ONE - $control,
            'escaped_ppm' => $escaped,
            'arrested_ppm' => $control - $escaped,
            'noise' => $byClass < 0 ? 'low' : ($byClass > 0 ? 'high' : 'normal'),
        ];
    }

    /**
     * What a night run is worth on average, as a part of its full pay: the whole of it when nobody stops the car, the part that a car which got away is paid, and a fine (a part
     * of the pay) when the driver is arrested, in which case the pay is nothing.
     *
     * @param  array<string, mixed>  $night
     * @param  array{clean_ppm: int, escaped_ppm: int, arrested_ppm: int}  $odds
     */
    public static function worth(array $night, array $odds): float
    {
        return ($odds['clean_ppm'] + $odds['escaped_ppm'] * $night['escape_pay_ppm'] / Ppm::ONE - $odds['arrested_ppm'] * $night['arrest']['fine_ppm'] / Ppm::ONE) / Ppm::ONE;
    }
}
