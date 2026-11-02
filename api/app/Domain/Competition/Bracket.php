<?php

declare(strict_types=1);

namespace App\Domain\Competition;

use App\Domain\Run\Rng;
use InvalidArgumentException;

/**
 * The bracket of a single elimination tournament: who meets whom in the first round (the draw) and how the rounds play out.
 *
 * The draw follows the rule of the design. The entrants are ranked by their rating (the better rating first, the id breaks a tie), so the best two are seeded
 * into opposite halves, the next two into opposite quarters, and the rest are drawn from the seed into the places that are left. When the number of entrants
 * is not a power of two the empty places (byes) go to the best seeds. Everything that is not fixed by the ranking is drawn from the seed, so the same
 * entrants and the same seed give the same bracket on every machine.
 */
final class Bracket
{
    /** The smallest size of a bracket (a power of two, and at least `$minimum`) that holds this many entrants. */
    public static function sizeFor(int $entrants, int $minimum = 2): int
    {
        $size = max(2, $minimum);

        if (($size & ($size - 1)) !== 0) {
            throw new InvalidArgumentException('The size of a bracket is a power of two.');
        }

        while ($size < $entrants) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * The pairs of the first round, in the order of the bracket (the winner of pair 0 meets the winner of pair 1, and so on). A pair with a null side is a bye.
     *
     * @param  list<array{id: int|string, rating: int}>  $entrants
     * @return list<array{0: int|string, 1: int|string|null}>
     */
    public static function draw(array $entrants, int $size, string $seed): array
    {
        $count = count($entrants);

        if ($count < 1 || $size < 2 || ($size & ($size - 1)) !== 0 || $count > $size || ($size > 2 && $count * 2 <= $size)) {
            throw new InvalidArgumentException('A bracket holds more than half of its size and no more than its size, which is a power of two (a smaller bracket is the one to draw).');
        }

        $ranked = array_values($entrants);
        usort($ranked, static fn (array $a, array $b): int => [$b['rating'], (string) $a['id']] <=> [$a['rating'], (string) $b['id']]);

        $ids = array_map(static fn (array $entrant): int|string => $entrant['id'], $ranked);

        if (count(array_unique($ids, SORT_REGULAR)) !== $count) {
            throw new InvalidArgumentException('Every entrant of a bracket has an id of its own.');
        }

        $pairs = intdiv($size, 2);
        $byes = $size - $count;
        $free = range(0, $pairs - 1);

        // Where the seeds that are fixed go: the first one to the first pair, the second to the last (the other half), the third and the fourth to a pair of each of
        // the two middle quarters. The seeds that have a bye take a pair of their own, drawn from the free ones.
        $anchors = [];

        if ($pairs >= 4) {
            $quarter = intdiv($pairs, 4);
            $anchors = [0, $pairs - 1];
            $middle = [1, 2];

            if (Rng::roll($seed, 'draw|quarters', 0) % 2 === 1) {
                $middle = [2, 1];
            }

            foreach ($middle as $order => $q) {
                $anchors[] = $q * $quarter + Rng::roll($seed, 'draw|anchor', $order) % $quarter;
            }
        } elseif ($pairs >= 2) {
            $anchors = [0, $pairs - 1];
        } else {
            $anchors = [0];
        }

        $anchors = array_slice($anchors, 0, $count);
        $placed = [];

        foreach ($anchors as $seedIndex => $pair) {
            $placed[$seedIndex] = $pair;
            $free = array_values(array_diff($free, [$pair]));
        }

        for ($seedIndex = count($anchors); $seedIndex < $byes; $seedIndex++) {
            $pick = Rng::roll($seed, 'draw|bye', $seedIndex) % count($free);
            $placed[$seedIndex] = $free[$pick];
            array_splice($free, $pick, 1);
        }

        $fixed = count($placed);
        $rest = array_slice($ids, $fixed);

        // The rest are shuffled from the seed (Fisher-Yates) and fill, in order, the pair of each seed that still needs an opponent and then the pairs that are empty.
        for ($i = count($rest) - 1; $i > 0; $i--) {
            $j = Rng::roll($seed, 'draw|shuffle', $i) % ($i + 1);
            [$rest[$i], $rest[$j]] = [$rest[$j], $rest[$i]];
        }

        $slots = array_fill(0, $pairs, [null, null]);

        foreach ($placed as $seedIndex => $pair) {
            $slots[$pair][0] = $ids[$seedIndex];
        }

        $next = 0;
        $byePairs = [];

        foreach ($placed as $seedIndex => $pair) {
            if ($seedIndex < $byes) {
                $byePairs[$pair] = true;
            } else {
                $slots[$pair][1] = $rest[$next++];
            }
        }

        foreach (array_keys($slots) as $pair) {
            if (isset($byePairs[$pair])) {
                continue;
            }

            foreach ([0, 1] as $side) {
                if ($slots[$pair][$side] === null) {
                    $slots[$pair][$side] = $rest[$next++];
                }
            }
        }

        return $slots;
    }

    /**
     * Plays the bracket: every pair meets, the winners meet in the next round, and so on to the final.
     *
     * @param  list<array{0: int|string, 1: int|string|null}>  $pairs  the draw
     * @param  callable(int|string, int|string, int, int): MatchResult  $match  plays the match of two entrants in a round (from 1) and a place of the round (from 0); side A is the first of them
     */
    public static function run(array $pairs, callable $match): BracketResult
    {
        $rounds = [];
        $reached = [];
        $margin = [];
        $current = $pairs;
        $round = 1;

        while (true) {
            $played = [];
            $winners = [];

            foreach ($current as $slot => [$a, $b]) {
                if ($b === null) {
                    $played[] = ['a' => $a, 'b' => null, 'winner' => $a, 'result' => null];
                    $winners[] = $a;

                    continue;
                }

                $result = $match($a, $b, $round, $slot);
                $winner = $result->winnerIsA() ? $a : $b;
                $loser = $result->winnerIsA() ? $b : $a;

                $played[] = ['a' => $a, 'b' => $b, 'winner' => $winner, 'result' => $result];
                $winners[] = $winner;
                $reached[$loser] = $round;
                $margin[$winner] = ($margin[$winner] ?? 0) + $result->marginOfWinnerMs();
                $margin[$loser] = ($margin[$loser] ?? 0) - $result->marginOfWinnerMs();
            }

            $rounds[] = $played;

            if (count($winners) === 1) {
                $reached[$winners[0]] = $round + 1;

                return new BracketResult($rounds, $winners[0], $reached, $margin);
            }

            $current = [];

            for ($i = 0; $i < count($winners); $i += 2) {
                $current[] = [$winners[$i], $winners[$i + 1]];
            }

            $round++;
        }
    }
}
