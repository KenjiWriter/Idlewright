<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The gigs of the game, played in the scenario game of the simulator: on every visit a player takes a gig with the main car at the share that says how many of the
 * visits they do it on (`$adoption`, by the kind of player), at most the daily limit of the level, and is paid what a gig is worth on average to the car (`GigEconomy`),
 * which a player who chooses among the offers gets a bit more of (`$choice`).
 *
 * - A gig does not take the tank: the car drives it on the fuel of the road and is back on its track, so the laps of the day are not touched. It is an addition to the
 *   income of the day, which is what the shift gate measures.
 * - With `$everyCar` the player sends every car of their fleet on every visit (the most that a player who plays normally does); with `$perDay` they take that many gigs a day
 *   whatever the visits (a script that farms them: the daily limit is the most that can be asked of the rules).
 */
final class GigsExtra extends PerkExtra
{
    /** How many of their visits a kind of player takes a gig on, when the rules do not say (an assumption of the study, not a number of the game). */
    public const ADOPTION = ['A' => 0.6, 'B' => 0.6, 'C' => 0.5];

    /** How much more than the mean offer a player gets who chooses among the offers of the board (an assumption of the study). */
    public const CHOICE = 1.2;

    private float $gigs = 0.0;

    /** @param array<string, float> $adoption the part of the visits that a gig is taken on, by the kind of player */
    public function __construct(
        private readonly GigEconomy $economy,
        private readonly array $adoption = self::ADOPTION,
        private readonly float $choice = self::CHOICE,
        private readonly ?int $perDay = null,
        private readonly bool $everyCar = false,
        private readonly string $label = 'gigs',
    ) {}

    public function name(): string
    {
        return $this->label;
    }

    public function day(DayState $state): DayEffect
    {
        $worth = $this->economy->perGig($state->level);

        if ($worth === null) {
            return $this->record($state, DayEffect::none());
        }

        $taken = $this->perDay !== null
            ? min($this->perDay, $this->economy->dailyLimit($state->level))
            : min($state->player->visitsPerDay * ($this->everyCar ? $state->player->fleetAt($state->rung) : ($this->adoption[$state->player->key] ?? 0.5)), $this->economy->dailyLimit($state->level));

        $this->gigs += $taken;

        return $this->record($state, new DayEffect(cash: $taken * $this->choice * $worth['cash'], xp: $taken * $this->choice * $worth['xp']));
    }

    public function report(): array
    {
        return ['gigs' => round($this->gigs, 1), 'choice' => $this->choice, ...$this->uplift()];
    }
}
