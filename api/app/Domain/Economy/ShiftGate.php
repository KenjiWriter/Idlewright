<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The gate of an extra of the scenario game (crates, storage units): it may move the day on which a player buys a car of the ladder, by no more than
 * the tolerance of the pace gate, from the day of the same scenario without it. It is not set against the days of the design: the scenario game
 * is out of those for its own reasons (the fleet), and what is asked here is only what the extra does on top of it. A day is the resolution of the
 * model, so a shift of a day is always allowed (`slack_days`).
 */
final class ShiftGate
{
    /**
     * @param  array<string, PlayerRun>  $baseline  the players played without the extra, by the key of the kind of player
     * @param  array<string, PlayerRun>  $runs  the same players played with it
     * @param  float|null  $tolerance  the share of the day that the extra may move it by: the tolerance of the pace gate when left out (a gift is held to a stricter one)
     */
    public function evaluate(EconomyRules $rules, array $baseline, array $runs, ?float $tolerance = null): GateReport
    {
        $tolerance ??= $rules->gateTolerance();
        $rows = [];

        foreach ($runs as $key => $run) {
            foreach ($baseline[$key]->purchases ?? [] as $purchase) {
                $day = $run->dayOf($purchase->rung);
                $allowed = max($tolerance * $purchase->day, $rules->gateSlackDays());

                $rows[] = new GateRow(
                    (string) $key,
                    $rules->rungName($purchase->rung),
                    $purchase->day,
                    $day,
                    $run->purchaseOf($purchase->rung)?->binding(),
                    $day !== null && abs($day - $purchase->day) <= $allowed,
                );
            }
        }

        return new GateReport($rows, $tolerance, $rules->gateSlackDays());
    }
}
