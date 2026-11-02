<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * What the payers of the scenario game showed (`PayerStudy`): the players played without any extra and with each of the missions and the paid extras, and by how much
 * sooner each of them buys the cars of the ladder than the same player who pays nothing.
 */
final readonly class PayerResult
{
    /**
     * @param  array<string, PlayerRun>  $baseline  the scenario game without extras
     * @param  array<string, array{label: string, runs: array<string, PlayerRun>}>  $variants  by key of the variant, then the runs by player
     * @param  array<string, array<string, PlayerRun>>  $missionLevels  a player who does every mission, when the missions pay this share of what they pay (the key, as text), by player
     */
    public function __construct(public array $baseline, public array $variants, public float $tolerance, public array $missionLevels = []) {}

    /** The day on which a player of a variant has the car of this rung, or null when never (the baseline when the variant is null). */
    public function dayOf(?string $variant, string $player, int $rung): ?int
    {
        $runs = $variant === null ? $this->baseline : ($this->variants[$variant]['runs'] ?? []);

        return isset($runs[$player]) ? $runs[$player]->dayOf($rung) : null;
    }

    /**
     * How much sooner (positive) or later (negative) a player of a variant has the car of this rung than the same player who pays nothing, as a share of the days of
     * the one who pays nothing: 0.2 is a fifth of the days saved. Null when either never has the car.
     */
    public function saved(string $variant, string $player, int $rung): ?float
    {
        $base = $this->dayOf(null, $player, $rung);
        $day = $this->dayOf($variant, $player, $rung);

        return $base === null || $day === null || $base === 0 ? null : ($base - $day) / $base;
    }

    /**
     * The places where a variant brings a car sooner than the tolerance of the pace gate allows for a shift: information for the owner (it fails nothing), because a
     * payer who is ahead of the pace of the design by more than the design leaves room for is what the study is for.
     *
     * @return list<array{variant: string, player: string, rung: int, saved: float}>
     */
    public function beyondTolerance(): array
    {
        $found = [];

        foreach ($this->variants as $key => $variant) {
            foreach (array_keys($variant['runs']) as $player) {
                foreach ($this->baseline[$player]->purchases ?? [] as $purchase) {
                    $saved = $this->saved($key, (string) $player, $purchase->rung);

                    if ($saved !== null && $saved > $this->tolerance) {
                        $found[] = ['variant' => $key, 'player' => (string) $player, 'rung' => $purchase->rung, 'saved' => $saved];
                    }
                }
            }
        }

        return $found;
    }
}
