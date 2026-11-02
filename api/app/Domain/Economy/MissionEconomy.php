<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The missions of the game that are drawn for a period, as the economy simulator sees them (the main missions are done once and are not part of it): for each period (the day, the week, the month) the slots of its set, each with the templates a
 * level can draw, their weights and what they are worth in tanks of the best car, the share of that worth that the rules pay in cash and in experience, and the
 * coins of the bonus for the whole set. The worth of a template already has its multiplier (`reward_share`) in it. Pure: `Content\GameMissions` makes it from the pack.
 *
 * Everything is an expected value, like the crates: a set pays the weighted mean of the worth of the templates that fit the level, slot by slot.
 */
final readonly class MissionEconomy
{
    /** How many days each period has, for the daily mean of what it pays (the month is 30 days, the week 7). */
    public const DAYS = ['daily' => 1, 'weekly' => 7, 'monthly' => 30];

    /**
     * @param  array<string, array{cash_ppm: int, xp_ppm: int, bonus_coins: int, slots: list<list<array{weight: int, worth: float, min_level: int, max_level: int}>>}>  $periods  by period
     */
    public function __construct(public array $periods) {}

    /** How many missions the set of a period has. */
    public function missionsIn(string $period): int
    {
        return count($this->periods[$period]['slots'] ?? []);
    }

    /** What the set of a period is worth at this level, in tanks of the best car: the mean of every slot over the templates that fit the level. */
    public function worthAt(string $period, int $level): float
    {
        $worth = 0.0;

        foreach ($this->periods[$period]['slots'] ?? [] as $templates) {
            $fitting = array_filter($templates, static fn (array $template): bool => $level >= $template['min_level'] && $level <= $template['max_level']);
            $weights = array_sum(array_column($fitting, 'weight'));

            foreach ($fitting as $template) {
                $worth += $template['weight'] / $weights * $template['worth'];
            }
        }

        return $worth;
    }

    /**
     * What every set that is done in a day, a week and a month pays in a day on average, in tanks of the best car (net of the fuel): the cash and the experience.
     *
     * @return array{cash: float, xp: float}
     */
    public function tanksPerDay(int $level, float $completion = 1.0): array
    {
        $cash = 0.0;
        $xp = 0.0;

        foreach (self::DAYS as $period => $days) {
            if (! isset($this->periods[$period])) {
                continue;
            }

            $worth = $completion * $this->worthAt($period, $level) / $days;
            $cash += $worth * $this->periods[$period]['cash_ppm'] / 1_000_000;
            $xp += $worth * $this->periods[$period]['xp_ppm'] / 1_000_000;
        }

        return ['cash' => $cash, 'xp' => $xp];
    }

    /**
     * The coins of the bonuses in a day on average, for a player who does each mission of a set with this chance, independently: the bonus comes with the whole set,
     * so the chance of it is the chance of each mission to the power of the missions of the set.
     */
    public function coinsPerDay(float $completion = 1.0): float
    {
        $coins = 0.0;

        foreach (self::DAYS as $period => $days) {
            if (isset($this->periods[$period])) {
                $coins += $completion ** $this->missionsIn($period) * $this->periods[$period]['bonus_coins'] / $days;
            }
        }

        return $coins;
    }
}
