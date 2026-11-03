<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The streak of days, played in the scenario game of the simulator: the player keeps it every day (the most it can pay, since a driver who breaks it gets less), and every day
 * the place in the cycle pays a part of a tank of the car in cash and experience (`MissionEconomy` does the same for the missions) and, on the days that give a crate, a free
 * crate of the tier that the level opens.
 *
 * - A tank is what the car drives in the offline window of its rung: it pays what the hours of the window pay after the fuel, and gives the experience of those hours.
 * - A free crate is worth what it would cost (`CrateEconomy::cost`): the most it can be worth, since the crate has the odds of every crate and the price is what a driver pays for them.
 * - The milestones pay on the day of the streak that they name, once.
 */
final class StreakExtra extends PerkExtra
{
    /**
     * @param  list<array{cash_ppm: int, xp_ppm: int, crates: int}>  $cycle  what each day of the cycle pays: the parts of a tank in ppm, and the crates
     * @param  array<int, array{cash_ppm: int, xp_ppm: int, crates: int}>  $milestones  what the day of the streak that is the key pays on top of the cycle
     * @param  float  $completion  the share of the days on which the goal is done (1 is a player who never breaks it)
     */
    public function __construct(
        private readonly array $cycle,
        private readonly array $milestones,
        private readonly CrateEconomy $crates,
        private readonly float $completion = 1.0,
        private readonly string $label = 'streak',
    ) {}

    public function name(): string
    {
        return $this->label;
    }

    public function day(DayState $state): DayEffect
    {
        $rules = $state->rules;
        $entry = $this->cycle[($state->day - 1) % count($this->cycle)];
        $milestone = $this->milestones[$state->day] ?? ['cash_ppm' => 0, 'xp_ppm' => 0, 'crates' => 0];
        $window = $rules->offlineWindowHours($state->rung);
        $tier = $this->crates->tierAt($rules, $state->level);
        $crateCost = $tier >= 1 ? $this->crates->cost($rules, $tier) : 0.0;

        $cash = ($entry['cash_ppm'] + $milestone['cash_ppm']) / 1_000_000 * $rules->netPerHour($state->rung) * $window + ($entry['crates'] + $milestone['crates']) * $crateCost;
        $xp = ($entry['xp_ppm'] + $milestone['xp_ppm']) / 1_000_000 * $rules->xpPerHourOfDriving() * $window;

        return $this->record($state, new DayEffect(cash: $this->completion * $cash, xp: $this->completion * $xp));
    }

    public function report(): array
    {
        return ['completion' => $this->completion, 'priced_by' => 'a free crate is worth its price (the most it can be worth)', ...$this->uplift()];
    }
}
