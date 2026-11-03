<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * Transmutation, played in the scenario game of the simulator for a player who has the coins for it: when the level opens a tier of parts (and the level is the one that opens
 * transmutation), the player turns the common parts of the tier in the slots that count to epic as soon as the cash is there, and the car earns more by what epic parts add
 * (the table of the pace of the rarities, `content/tables/rarity-pace.json`). The coins are not cash and do not move the ladder; the cash is what each step costs, a multiple of
 * the price of the model (`transmute.cash_ppm`).
 *
 * - The parts are the ones that the anchors price (`EconomyRules::partPrice`), a tier and a slot at a time.
 * - The gain is the sum of the epic gains of the slots, as the table gives it for a car with those three parts.
 * - When the level opens the next tier, the parts of the new tier are common again and the player does it again.
 */
final class TransmuteExtra implements Extra
{
    private int $tier = 0;

    private bool $done = false;

    private float $gain = 0.0;

    private float $spent = 0.0;

    /** The gain of the last tier whose parts were turned to epic (the gain falls to nothing for a day when a new tier opens, until the cash is there). */
    private float $lastGain = 0.0;

    private int $transmutations = 0;

    private ?int $firstDay = null;

    /**
     * @param  array<string, float>  $epicGain  by slot, what epic parts add to the laps in an hour (0.2 is a fifth)
     * @param  list<float>  $cashSteps  what each step costs as a multiple of the price of the part (common to uncommon, to rare, to epic)
     */
    public function __construct(private readonly CrateEconomy $crates, private readonly array $epicGain, private readonly array $cashSteps, private readonly int $minPlayerLevel) {}

    public function name(): string
    {
        return 'transmute';
    }

    public function day(DayState $state): DayEffect
    {
        $tier = $this->crates->tierAt($state->rules, $state->level);

        if ($state->level < $this->minPlayerLevel || $tier < 1) {
            return DayEffect::none();
        }

        if ($tier !== $this->tier) {
            $this->tier = $tier;
            $this->done = false;
            $this->gain = 0.0;
        }

        if ($this->done) {
            return new DayEffect(0.0, 1.0 + $this->gain);
        }

        $cost = 0.0;
        $gain = 0.0;

        foreach ($this->epicGain as $slot => $slotGain) {
            $price = $state->rules->partPrice($tier, $slot);

            if ($price !== null) {
                $cost += $price * array_sum($this->cashSteps);
                $gain += $slotGain;
            }
        }

        if ($cost <= 0.0 || $state->cash + $state->income < $cost) {
            return DayEffect::none();
        }

        $this->done = true;
        $this->gain = $gain;
        $this->lastGain = $gain;
        $this->spent += $cost;
        $this->transmutations += count($this->epicGain);
        $this->firstDay ??= $state->day;

        return new DayEffect(-$cost, 1.0 + $gain);
    }

    public function stepCostDelta(EconomyRules $rules, int $rung): float
    {
        return 0.0;
    }

    public function report(): array
    {
        return [
            'transmutations' => $this->transmutations,
            'spent' => round($this->spent, 2),
            'uplift' => round($this->lastGain, 4),
            'tier' => $this->tier,
            'first_day' => $this->firstDay,
            'priced_by' => 'the anchors for the parts (tiers 1 to 10); the gain is the table of the pace of the rarities',
        ];
    }
}
