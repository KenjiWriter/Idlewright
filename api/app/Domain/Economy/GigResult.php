<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The gigs played on top of the scenario game of the simulator: the players without them, and with them in each plan (a player who takes a gig on the visits that
 * the study assumes, one who takes one on every visit, one who takes as many as the rules allow in a day) with the shift of the days of the ladder of each against the
 * players without them.
 */
final readonly class GigResult
{
    /**
     * @param  array<string, PlayerRun>  $baseline  the players without the gigs, by the key of the kind of player
     * @param  array<string, array{label: string, runs: array<string, PlayerRun>, gate: GateReport, binding: bool}>  $plans  by the name of the plan; a binding plan is one the gate is asked of
     * @param  list<array{level: int, cash: float, xp: float, hours: float, share: float, limit: int}>  $worth  what a gig is worth to the main car at the levels that were asked about
     */
    public function __construct(public array $baseline, public array $plans, public array $worth = []) {}

    /** Whether every plan that the gate is asked of moves no day of the ladder by more than the tolerance. */
    public function passed(): bool
    {
        foreach ($this->plans as $plan) {
            if ($plan['binding'] && ! $plan['gate']->passed()) {
                return false;
            }
        }

        return true;
    }
}
