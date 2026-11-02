<?php

declare(strict_types=1);

namespace App\Domain\Economy;

use Closure;

/**
 * What transmutation costs next to what merging costs (the rule the owner approved is that the cash of a step is about half of what merging would cost for the same step, so
 * that merging stays worth it for a driver without coins, and transmutation is a convenience and not a bargain), and what the premium coins of it come to in money. Pure: the
 * costs of merging come as the table of `MergeEconomy`, in units of p (the price of the model of the part), and the transmutation as functions of the tier.
 *
 * The step from a rarity to the next costs, when merged, what the better rarity costs from common parts less what the part it is made from cost (the part that is merged away).
 * A step of transmutation costs cash (a multiple of p) and coins (by the tier); the coins are valued at the price per coin of the packs.
 */
final readonly class TransmuteEconomy
{
    /** The rarities that transmutation makes, from the one above the common, and the step each one is. */
    public const STEPS = ['uncommon', 'rare', 'epic'];

    /**
     * @param  list<array{rarity: string, cost: float}>  $merge  `MergeEconomy::steps()`: what a part of each rarity costs to make from common ones, in units of p
     * @param  list<float>  $cashP  the cash of the three steps in units of p
     * @param  Closure(int, int): int  $coinsOf  the coins of a step (0, 1, 2) for a part of a tier
     * @param  float  $zlotyPerCoin  what a coin costs in the packs (the dearest, the smallest pack, and the cheapest)
     */
    public function __construct(private array $merge, private array $cashP, private Closure $coinsOf, private array $zlotyPerCoin) {}

    /**
     * The three steps next to merging: the cost of the step when merged (in p), the cash of transmutation and its share of that, and the cheapest cost of the whole way from
     * a common part (cash in p, coins and money for a part of a tier).
     *
     * @return array{steps: list<array{rarity: string, merge_p: float, cash_p: float, share: float}>, whole_way: array{merge_p: float, cash_p: float, share: float}}
     */
    public function steps(): array
    {
        $costs = ['common' => 1.0];

        foreach ($this->merge as $row) {
            $costs[$row['rarity']] = $row['cost'];
        }

        $previous = 'common';
        $steps = [];
        $cash = 0.0;

        foreach (self::STEPS as $index => $rarity) {
            $merge = $costs[$rarity] - $costs[$previous];
            $steps[] = ['rarity' => $rarity, 'merge_p' => $merge, 'cash_p' => $this->cashP[$index], 'share' => $merge > 0 ? $this->cashP[$index] / $merge : 0.0];
            $cash += $this->cashP[$index];
            $previous = $rarity;
        }

        $mergeWhole = $costs['epic'] - $costs['common'];

        return ['steps' => $steps, 'whole_way' => ['merge_p' => $mergeWhole, 'cash_p' => $cash, 'share' => $mergeWhole > 0 ? $cash / $mergeWhole : 0.0]];
    }

    /**
     * The coins of each step for a part of a tier, and what they come to in money (zloty) at the cheapest and the dearest price of a coin.
     *
     * @return array{coins: list<int>, total: int, zloty_min: float, zloty_max: float}
     */
    public function coins(int $tier): array
    {
        $coins = array_map(fn (int $step): int => ($this->coinsOf)($step, $tier), [0, 1, 2]);
        $total = array_sum($coins);

        return ['coins' => $coins, 'total' => $total, 'zloty_min' => $total * min($this->zlotyPerCoin), 'zloty_max' => $total * max($this->zlotyPerCoin)];
    }

    /**
     * The coins and the money of a legendary part that could be got by merging epic parts that were transmuted from common ones: the number of epic parts that merging to
     * legendary uses on average (two for every attempt), and what the coins of that come to. It is why a transmuted part is not used to merge.
     *
     * @param  int  $attemptsToLegendary  how many attempts merging to legendary takes on average
     * @return array{epics: float, coins: int, zloty_min: float, zloty_max: float}
     */
    public function legendaryThroughMerging(int $tier, float $attemptsToLegendary): array
    {
        $epics = 2 * $attemptsToLegendary;
        $coins = (int) round($epics * $this->coins($tier)['total']);

        return ['epics' => $epics, 'coins' => $coins, 'zloty_min' => $coins * min($this->zlotyPerCoin), 'zloty_max' => $coins * max($this->zlotyPerCoin)];
    }
}
