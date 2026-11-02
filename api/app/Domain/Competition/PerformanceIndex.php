<?php

declare(strict_types=1);

namespace App\Domain\Competition;

use InvalidArgumentException;

/**
 * The performance index (PI) of a car: how strong it is, as a single number, from its statistics and not from a lap time. The lap time of a car depends on the
 * track and on how the game derives it, and it saturates at the top (the stat ratios of a lap are capped), so a class made out of lap times could not tell a good
 * car from a very good one. The statistics are what every way of deriving a lap time starts from.
 *
 *   ratio of a stat = the stat of the car over the same stat of the reference car, never more than the cap
 *   PI = reference PI * (weighted mean of the ratios)
 *
 * so the reference car has exactly the reference PI. Integers only: the statistics come in thousandths (the one place where a float becomes an integer is
 * `CarPerformance`, which is next to the pipeline that makes the floats) and everything else is a rounded division.
 */
final class PerformanceIndex
{
    private const PPM = 1_000_000;

    /**
     * @param  array<string, int>  $statsMilli  the statistics of the car, in thousandths, by key
     * @param  array<string, int>  $referenceMilli  the same of the reference car
     * @param  array<string, int>  $weightsPpm  how much each statistic counts, by key; the keys are the ones that count, and they need not add up to a million
     * @param  int  $capPpm  the most a ratio counts for (4 000 000 is four times the reference car)
     * @param  int  $referencePi  what the reference car is worth
     */
    public static function of(array $statsMilli, array $referenceMilli, array $weightsPpm, int $capPpm, int $referencePi): int
    {
        $total = array_sum($weightsPpm);

        if ($weightsPpm === [] || $total < 1 || $capPpm < 1 || $referencePi < 1) {
            throw new InvalidArgumentException('A performance index needs weights, a cap and a reference value that are positive.');
        }

        $sum = 0;

        foreach ($weightsPpm as $stat => $weight) {
            $reference = $referenceMilli[$stat] ?? 0;

            if ($reference < 1) {
                throw new InvalidArgumentException("The reference car has no value for the statistic {$stat}.");
            }

            $value = max(0, $statsMilli[$stat] ?? 0);
            $ratio = min($capPpm, intdiv($value * self::PPM + intdiv($reference, 2), $reference));
            $sum += $weight * $ratio;
        }

        $denominator = $total * self::PPM;

        return intdiv($referencePi * $sum + intdiv($denominator, 2), $denominator);
    }
}
