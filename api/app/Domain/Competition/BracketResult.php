<?php

declare(strict_types=1);

namespace App\Domain\Competition;

/** How a bracket played out: every round with its matches, the champion, and how far each entrant got. */
final readonly class BracketResult
{
    /**
     * @param  list<list<array{a: int|string, b: int|string|null, winner: int|string, result: MatchResult|null}>>  $rounds  a match without a result is a bye
     * @param  array<int|string, int>  $reached  the round in which an entrant lost, and the number of rounds plus one for the champion; an entrant that only had byes and lost nothing is the champion
     * @param  array<int|string, int>  $marginMs  the sum of the margins of an entrant over its matches (what it won by, less what it lost by), in ms
     */
    public function __construct(public array $rounds, public int|string $champion, public array $reached, public array $marginMs) {}

    /** The number of rounds of the bracket. */
    public function roundCount(): int
    {
        return count($this->rounds);
    }

    /**
     * The entrants from the best finish to the worst: the champion, then the finalist, then the entrants that lost the semifinal, and so on. Those who lost in the same
     * round are told apart by the margin they had (the bigger first) and then by their place in `$order`, which is the order the caller prefers (for example the seeding).
     *
     * @param  list<int|string>  $order
     * @return list<int|string>
     */
    public function ranking(array $order): array
    {
        $position = array_flip(array_map('strval', $order));
        $ids = array_values($order);

        usort($ids, fn (int|string $a, int|string $b): int => [
            -($this->reached[$a] ?? 0),
            -($this->marginMs[$a] ?? 0),
            $position[(string) $a],
        ] <=> [
            -($this->reached[$b] ?? 0),
            -($this->marginMs[$b] ?? 0),
            $position[(string) $b],
        ]);

        return $ids;
    }
}
