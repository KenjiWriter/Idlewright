<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The auto-refill, played in the scenario game of the simulator: every day the player uses a number of charges, and every charge fills the tank of the main car when
 * it runs dry, so the car drives one more window of its rung, with no fuel paid in cash and without anyone there. The charges come from packs of coins.
 *
 * - The hours of the car do not go over the day, nor over what the car drives between two breakdowns for every visit of the player (a breakdown stands the car until
 *   the player comes back, and that is what limits a car that is never short of fuel).
 * - The hours that the charges add are hours of the car alone: the activity of the player (its tournaments and courses) does not multiply them.
 * - Only the main car is filled: the wrecks stand when their fuel runs out, so what the report says of a player with many cars is lower than the game gives.
 */
final class RefillExtra extends PerkExtra
{
    private float $charges = 0.0;

    public function __construct(private readonly PerkEconomy $perks, private readonly float $chargesPerDay) {}

    public function name(): string
    {
        return "refill-{$this->chargesPerDay}";
    }

    public function day(DayState $state): DayEffect
    {
        $rules = $state->rules;
        $window = $rules->offlineWindowHours($state->rung);
        $own = $state->player->hoursPerDay($window);
        $reach = min(PerkEconomy::DAY_HOURS, $state->player->visitsPerDay * $this->perks->hoursBetweenBreakdowns($rules, $state->rung));
        $extra = max(0.0, min($own + $this->chargesPerDay * $window, $reach) - $own);

        $this->charges += $window > 0.0 ? $extra / $window : 0.0;

        return $this->record($state, $this->extraHours($state, $extra));
    }

    public function report(): array
    {
        $days = max(1, $this->uplift()['days']);
        $coins = $this->charges / $days * 30 * $this->perks->coinsPerCharge;

        return [
            'charges_a_day' => $this->chargesPerDay,
            'charges_used' => round($this->charges, 2),
            'coins_per_30d' => round($coins, 2),
            'price_per_30d' => round($this->perks->priceOfCoins($coins), 2),
            'currency' => $this->perks->currency,
            ...$this->uplift(),
        ];
    }
}
