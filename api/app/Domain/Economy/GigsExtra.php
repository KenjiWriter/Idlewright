<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * The gigs of the game, played in the scenario game of the simulator: on every visit a player takes a gig with the main car at the share that says how many of the
 * visits they do it on (`$adoption`, by the kind of player), at most the daily limit of the level, and is paid what a gig is worth on average to the car (`GigEconomy`),
 * which a player who chooses among the offers gets a bit more of (`$choice`).
 *
 * - A gig burns the fuel of the road out of the tank (like a journey) and puts its kilometres on the car, but not at the pace of the laps (a dozen times less an hour), so the car is back on its track with most of the tank and the laps of the day are not touched. It is an addition to the
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

    /**
     * How often a player who is playing at night takes the night run (an assumption of the study, not a number of the game): it is a risk, and only a part of the players take it.
     */
    public const NIGHT_TAKE = ['A' => 0.5, 'B' => 0.4, 'C' => 0.3];

    /** The part of the day that is night in the game (seven hours of twenty-four): the chance that a visit falls in one. */
    private const NIGHT_SHARE = 7 / 24;

    private float $gigs = 0.0;

    private float $nights = 0.0;

    /**
     * @param  array<string, float>  $adoption  the part of the visits that a gig is taken on, by the kind of player
     * @param  array<string, float>  $nightTake  the part of the nights that a night run is taken on, by the kind of player
     */
    public function __construct(
        private readonly GigEconomy $economy,
        private readonly array $adoption = self::ADOPTION,
        private readonly float $choice = self::CHOICE,
        private readonly ?int $perDay = null,
        private readonly bool $everyCar = false,
        private readonly string $label = 'gigs',
        private readonly array $nightTake = self::NIGHT_TAKE,
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

        $limit = $this->economy->dailyLimit($state->level);
        $night = $this->economy->perNightRun($state->level);

        // A player who takes as many as the rules allow fills the day with the night runs first (they pay most); one who plays normally takes them on the nights that a visit
        // falls in and that they take the risk on, and the gigs of the day are what is left of the limit.
        if ($this->perDay !== null) {
            $nights = $night === null ? 0.0 : (float) min($this->economy->nightLimit(), $limit);
            $taken = min($this->perDay, $limit) - $nights;
        } else {
            $taken = min($state->player->visitsPerDay * ($this->everyCar ? $state->player->fleetAt($state->rung) : ($this->adoption[$state->player->key] ?? 0.5)), $limit);
            $reach = (1 - (1 - self::NIGHT_SHARE) ** $state->player->visitsPerDay) * ($this->nightTake[$state->player->key] ?? 0.3);
            $nights = $night === null ? 0.0 : max(0.0, min($this->economy->nightLimit(), $reach, $limit - $taken));
        }

        $this->gigs += $taken;
        $this->nights += $nights;

        $cash = $taken * $this->choice * $worth['cash'] + ($night === null ? 0.0 : $nights * $night['cash']);
        $xp = $taken * $this->choice * $worth['xp'] + ($night === null ? 0.0 : $nights * $night['xp']);

        return $this->record($state, new DayEffect(cash: $cash, xp: $xp));
    }

    public function report(): array
    {
        return ['gigs' => round($this->gigs, 1), 'nights' => round($this->nights, 1), 'choice' => $this->choice, ...$this->uplift()];
    }
}
