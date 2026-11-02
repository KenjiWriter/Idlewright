<?php

declare(strict_types=1);

namespace App\Domain\Competition;

use App\Domain\Run\Rng;
use App\Domain\Settlement\Ppm;
use InvalidArgumentException;

/**
 * A match between two cars, played on the laps that were frozen with them: nothing here knows how a lap time is made, it only reads the table of laps of each side
 * (track, weather, milliseconds), so it plays the same whatever the game does to make them.
 *
 *   run time = lap * (1 + luck of the run)      the quicker run wins the run
 *   match    = the side that wins most of the runs (an odd number of them)
 *
 * The luck comes from the seed, the key of the match and the run (`Rng`, sha256), through the table of the luck, so the same inputs give the same match on every machine
 * and playing it again changes nothing. The two sides draw their luck apart, and a tie of a run is decided by the seed too.
 */
final class MatchEngine
{
    public const SIDE_A = 'a';

    public const SIDE_B = 'b';

    /**
     * @param  array<string, array<string, int>>  $lapsA  the laps of side A by track and weather, in ms
     * @param  array<string, array<string, int>>  $lapsB  the same for side B
     * @param  list<array{track: string, weather: string}>  $runs  the runs to drive, in order, an odd number of them
     * @param  string  $seed  the secret of the event
     * @param  string  $matchKey  tells the matches of an event apart (it goes into every roll)
     * @param  int  $sigmaPpm  the width of the luck, as a part of the lap time
     *
     * @throws InvalidArgumentException when the number of runs is not odd or a side has no lap for a run
     */
    public static function play(array $lapsA, array $lapsB, array $runs, string $seed, string $matchKey, LuckTable $luck, int $sigmaPpm): MatchResult
    {
        if ($runs === [] || count($runs) % 2 === 0) {
            throw new InvalidArgumentException('A match is played in an odd number of runs.');
        }

        $played = [];
        $aWins = 0;
        $bWins = 0;
        $margin = 0;

        foreach (array_values($runs) as $index => $run) {
            $lapA = self::lap($lapsA, $run, 'A');
            $lapB = self::lap($lapsB, $run, 'B');

            $runA = $lapA + Ppm::mul($lapA, $luck->luckPpm(Rng::roll($seed, "luck|{$matchKey}|{$index}|a", 0), $sigmaPpm));
            $runB = $lapB + Ppm::mul($lapB, $luck->luckPpm(Rng::roll($seed, "luck|{$matchKey}|{$index}|b", 0), $sigmaPpm));

            $winner = match (true) {
                $runA < $runB => self::SIDE_A,
                $runB < $runA => self::SIDE_B,
                default => Rng::roll($seed, "tie|{$matchKey}|{$index}", 0) % 2 === 0 ? self::SIDE_A : self::SIDE_B,
            };

            $winner === self::SIDE_A ? $aWins++ : $bWins++;
            $margin += $runB - $runA;
            $played[] = ['track' => $run['track'], 'weather' => $run['weather'], 'a_ms' => $lapA, 'b_ms' => $lapB, 'a_run_ms' => $runA, 'b_run_ms' => $runB, 'winner' => $winner];
        }

        return new MatchResult($played, $aWins, $bWins, $aWins > $bWins ? self::SIDE_A : self::SIDE_B, $margin);
    }

    /**
     * @param  array<string, array<string, int>>  $laps
     * @param  array{track: string, weather: string}  $run
     */
    private static function lap(array $laps, array $run, string $side): int
    {
        $ms = $laps[$run['track']][$run['weather']] ?? null;

        if (! is_int($ms) || $ms < 1) {
            throw new InvalidArgumentException("Side {$side} has no lap on {$run['track']} in {$run['weather']}.");
        }

        return $ms;
    }
}
