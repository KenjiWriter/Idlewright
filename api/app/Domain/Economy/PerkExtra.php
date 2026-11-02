<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * What the extras of the paid kind and the missions have in common: they never change what a step of the ladder costs, and they say how much they added to the
 * cash and the experience of the first thirty days (as a share of what the player earned in them without the extra), which is what a payer is compared with a player
 * who does not pay by.
 *
 * The cash that an extra adds is counted against the income of the cars of the day (the main car and the wrecks), and the experience against the experience that the
 * driving gave.
 */
abstract class PerkExtra implements Extra
{
    /** The days that the first look at an extra covers. */
    public const WINDOW_DAYS = 30;

    private int $days = 0;

    private float $baseCash = 0.0;

    private float $baseXp = 0.0;

    private float $addedCash = 0.0;

    private float $addedXp = 0.0;

    private float $addedCashTotal = 0.0;

    private float $addedXpTotal = 0.0;

    public function stepCostDelta(EconomyRules $rules, int $rung): float
    {
        return 0.0;
    }

    /** Counts what the effect of a day adds (to the first thirty days and to all of them) and hands it back. */
    protected function record(DayState $state, DayEffect $effect): DayEffect
    {
        $income = $state->income + $state->otherIncome;
        $cash = $effect->cash + $income * ($effect->incomeFactor - 1.0);
        $xp = $effect->xp + $state->xpToday * ($effect->xpFactor - 1.0);

        $this->days++;
        $this->addedCashTotal += $cash;
        $this->addedXpTotal += $xp;

        if ($state->day <= self::WINDOW_DAYS) {
            $this->baseCash += $income;
            $this->baseXp += $state->xpToday;
            $this->addedCash += $cash;
            $this->addedXp += $xp;
        }

        return $effect;
    }

    /**
     * What every extra says: the share that it added to the cash and to the experience of the first thirty days, and what it added to all the days it was played.
     *
     * @return array{uplift_cash_30d: float, uplift_xp_30d: float, added_cash: float, added_xp: float, days: int}
     */
    protected function uplift(): array
    {
        return [
            'uplift_cash_30d' => $this->baseCash > 0.0 ? round($this->addedCash / $this->baseCash, 4) : 0.0,
            'uplift_xp_30d' => $this->baseXp > 0.0 ? round($this->addedXp / $this->baseXp, 4) : 0.0,
            'added_cash' => round($this->addedCashTotal, 2),
            'added_xp' => round($this->addedXpTotal, 2),
            'days' => $this->days,
        ];
    }

    /**
     * What a car that drives for more hours than the player pays for adds to a day: the hours it drives alone at night, at what an hour pays before the fuel (the fuel of
     * those hours is paid with a charge or with the pass, not with cash), less the upkeep, and their experience; and the fuel that is not paid for the hours that the
     * player drives (`$freeFuelHours`, of the hours the player pays for).
     */
    protected function extraHours(DayState $state, float $hours, float $freeFuelHours = 0.0): DayEffect
    {
        $rules = $state->rules;

        if ($hours <= 0.0 && $freeFuelHours <= 0.0) {
            return DayEffect::none();
        }

        $cash = $hours * ($rules->grossPerHour($state->rung) - $rules->upkeepShare() * $rules->netPerHour($state->rung))
            + $freeFuelHours * $rules->fuelPerHour($state->rung) * $state->player->activity;

        return new DayEffect(cash: $cash, xp: $hours * $rules->xpPerHourOfDriving());
    }
}
