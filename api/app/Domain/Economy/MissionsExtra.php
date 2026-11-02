<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The missions of the game, played in the scenario game of the simulator: every day the player does the sets of the day, of the week and of the month at the share
 * of the missions that it completes (1 is a player who does everything, 0.6 one who does most of it), and is paid what the missions pay: a part of the worth of each
 * mission, in the cash and the experience of a tank of the car that the player has (`MissionEconomy`).
 *
 * - A tank is what the car drives in the offline window of its rung: it pays what the hours of the window pay after the fuel, and gives the experience of those hours.
 * - The sets of the week and of the month are paid a day at a time, a seventh and a thirtieth of them: an expected value, so that nothing is random.
 * - The coins of the bonus for a whole set are counted too, for a player who does each mission with the chance of the share: the coins are not cash and do not move the
 *   ladder, but they are what makes a free player a payer: the report says how many a month and how much of a month of VIP they are.
 * - The missions ask for work that is not free (a crate is bought, a part is upgraded): what the player spends doing it is not taken from the cash, and `missions:budget`
 *   with `--audit` says what the work is worth against what it costs.
 */
final class MissionsExtra extends PerkExtra
{
    private float $coins = 0.0;

    public function __construct(private readonly MissionEconomy $missions, private readonly float $completion = 1.0, private readonly string $label = 'missions') {}

    public function name(): string
    {
        return $this->label;
    }

    public function day(DayState $state): DayEffect
    {
        $rules = $state->rules;
        $window = $rules->offlineWindowHours($state->rung);
        $tanks = $this->missions->tanksPerDay($state->level, $this->completion);

        $this->coins += $this->missions->coinsPerDay($this->completion);

        return $this->record($state, new DayEffect(
            cash: $tanks['cash'] * $rules->netPerHour($state->rung) * $window,
            xp: $tanks['xp'] * $rules->xpPerHourOfDriving() * $window,
        ));
    }

    public function report(): array
    {
        return [
            'completion' => $this->completion,
            'coins' => round($this->coins, 2),
            'coins_per_30d' => round($this->missions->coinsPerDay($this->completion) * 30, 2),
            ...$this->uplift(),
        ];
    }
}
