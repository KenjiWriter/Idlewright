<?php

declare(strict_types=1);

namespace App\Domain\Settlement;

use InvalidArgumentException;

/**
 * Splits the pool of a single-elimination tournament.
 *
 *   pool = floor(entry fees * 90%)            the rest is the 10% rake, a sink of the economy
 *   share of a place = floor(pool * basis points / 10 000)
 *   the few cents left over by rounding go to the winner
 *
 * The shares are in basis points (10 000 = the whole pool) and are paid to every
 * player in the group: one winner, one finalist, two semifinalists and so on.
 */
final class TournamentPayout
{
    public const RAKE_BASIS_POINTS = 1_000;

    private const WHOLE = 10_000;

    /**
     * Group definitions per bracket size: [stage, number of players, basis points for each].
     *
     * @var array<int, list<array{0: string, 1: int, 2: int}>>
     */
    private const SHARES = [
        8 => [['winner', 1, 5_000], ['finalist', 1, 2_500], ['semifinalist', 2, 1_250]],
        16 => [['winner', 1, 4_000], ['finalist', 1, 2_000], ['semifinalist', 2, 1_000], ['quarterfinalist', 4, 500]],
        32 => [['winner', 1, 3_500], ['finalist', 1, 1_700], ['semifinalist', 2, 900], ['quarterfinalist', 4, 450], ['round_of_16', 8, 150]],
    ];

    /** @return list<int> the bracket sizes with a payout table */
    public static function sizes(): array
    {
        return array_keys(self::SHARES);
    }

    public static function split(int $players, int $entryFeeCents): PayoutResult
    {
        if (! isset(self::SHARES[$players])) {
            throw new InvalidArgumentException("No payout table for {$players} players. Sizes: ".implode(', ', self::sizes()).'.');
        }

        if ($entryFeeCents <= 0) {
            throw new InvalidArgumentException('The entry fee must be positive.');
        }

        $total = $players * $entryFeeCents;
        $pool = intdiv($total * (self::WHOLE - self::RAKE_BASIS_POINTS), self::WHOLE);

        $groups = [];
        $paid = 0;

        foreach (self::SHARES[$players] as [$stage, $count, $basisPoints]) {
            $each = intdiv($pool * $basisPoints, self::WHOLE);
            $groups[] = ['stage' => $stage, 'count' => $count, 'each_cents' => $each];
            $paid += $count * $each;
        }

        return new PayoutResult($total, $pool, $total - $pool, $groups, $pool - $paid);
    }

    /**
     * The split of a pool that only the real players of a bracket paid into (the other places are held by bots, which pay nothing and take nothing): the pool is
     * the fees of the real players less the rake, and the real players take the places in the order they finished. When there are fewer real players than paying
     * places, the shares of the places they take are scaled up to the whole pool, so nothing is left over and nobody is paid for a place that is a bot's. With as
     * many real players as the bracket has places this is `split`.
     *
     * @param  int  $realPlayers  how many real players paid the fee, at least one and no more than the size
     */
    public static function splitAmong(int $realPlayers, int $size, int $entryFeeCents): PayoutResult
    {
        if (! isset(self::SHARES[$size])) {
            throw new InvalidArgumentException("No payout table for {$size} players. Sizes: ".implode(', ', self::sizes()).'.');
        }

        if ($realPlayers < 1 || $realPlayers > $size) {
            throw new InvalidArgumentException("A bracket of {$size} has between one and {$size} real players, not {$realPlayers}.");
        }

        if ($entryFeeCents <= 0) {
            throw new InvalidArgumentException('The entry fee must be positive.');
        }

        $total = $realPlayers * $entryFeeCents;
        $pool = intdiv($total * (self::WHOLE - self::RAKE_BASIS_POINTS), self::WHOLE);

        $places = [];

        foreach (self::SHARES[$size] as [$stage, $count, $basisPoints]) {
            for ($i = 0; $i < $count; $i++) {
                $places[] = [$stage, $basisPoints];
            }
        }

        $occupied = array_slice($places, 0, $realPlayers);
        $shares = array_sum(array_map(static fn (array $place): int => $place[1], $occupied));

        $groups = [];
        $paid = 0;

        foreach ($occupied as [$stage, $basisPoints]) {
            $each = intdiv($pool * $basisPoints, $shares);
            $paid += $each;
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['stage'] === $stage) {
                $groups[$last]['count']++;
            } else {
                $groups[] = ['stage' => $stage, 'count' => 1, 'each_cents' => $each];
            }
        }

        return new PayoutResult($total, $pool, $total - $pool, $groups, $pool - $paid);
    }

    /** Basis points paid to the players of one bracket size; always 10 000. */
    public static function basisPointsPaid(int $players): int
    {
        return array_sum(array_map(static fn (array $group): int => $group[1] * $group[2], self::SHARES[$players] ?? []));
    }
}
