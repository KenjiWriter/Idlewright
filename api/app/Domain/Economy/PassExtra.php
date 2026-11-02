<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The pass of the endless refill, played in the scenario game of the simulator: for the days of the pass the tank of the main car is filled for nothing whenever it runs
 * dry, so the car drives all day long between two breakdowns, and no fuel is paid in cash. It is bought again when it runs out. The same limits as for the charges
 * hold (`RefillExtra`): the day, and the time between two breakdowns for every visit of the player; and the hours that the pass adds are the car's alone.
 */
final class PassExtra extends PerkExtra
{
    public function __construct(private readonly PerkEconomy $perks) {}

    public function name(): string
    {
        return 'pass';
    }

    public function day(DayState $state): DayEffect
    {
        $rules = $state->rules;
        $own = $state->player->hoursPerDay($rules->offlineWindowHours($state->rung));
        $reach = min(PerkEconomy::DAY_HOURS, $state->player->visitsPerDay * $this->perks->hoursBetweenBreakdowns($rules, $state->rung));

        // The fuel of the hours that the player pays for is free too, and the car goes on by itself for the hours up to the next breakdown.
        return $this->record($state, $this->extraHours($state, max(0.0, $reach - $own), $own));
    }

    public function report(): array
    {
        return [
            'price_per_30d' => round($this->perks->passPriceMinor * 30 / $this->perks->passDays / 100, 2),
            'currency' => $this->perks->currency,
            ...$this->uplift(),
        ];
    }
}
