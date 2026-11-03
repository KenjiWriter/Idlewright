<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Sets of parts to try a car with, taken from what the shop sells: the best part of each slot among those of a tier or lower (at the common rarity), and the best parts
 * at a rare +4, an epic +8 and a legendary +12. The reports of the competition use them to see where the classes fall and what moving a car up a class does. The fit of the
 * parts to the car (the layout of the engine, the booster for an intercooler) is not checked, so a set is an upper bound of what a car with those parts could be.
 */
final class PartSets
{
    /** The tiers that make a set of their own. */
    public const TIERS = [1, 2, 3, 4, 5, 7, 9];

    public function __construct(private readonly Catalog $catalog, private readonly Rules $rules) {}

    /**
     * Every set by name, from the stock car up: `stock`, `T1` ... `T9`, `rare+4`, `epic+8` and `best+12`.
     *
     * @return array<string, array<string, array{slug: string, rarity: int, upgrade: int}>>
     */
    public function all(): array
    {
        $sets = ['stock' => []];

        foreach (self::TIERS as $tier) {
            $sets["T{$tier}"] = $this->mounted($this->best($tier), 1, 0);
        }

        $best = $this->best(null);
        $sets['rare+4'] = $this->mounted($best, 3, 4);
        $sets['epic+8'] = $this->mounted($best, 4, 8);
        $sets['best+12'] = $this->mounted($best, 5, 12);

        return $sets;
    }

    /**
     * The strongest part of the shop in every slot of the model among those of this tier or lower: the one of the highest tier (the first on the shelf among equals).
     *
     * @return array<string, string> the slug of the part by slot
     */
    public function best(?int $upToTier): array
    {
        $slots = $this->rules->model()->slotKeys();
        $best = [];

        foreach ($this->rules->shopParts() as $slug) {
            $part = $this->catalog->part($slug);

            if ($part === null || ! in_array($part['slot'], $slots, true) || ($upToTier !== null && $part['tier'] > $upToTier)) {
                continue;
            }

            if (! isset($best[$part['slot']]) || $part['tier'] > $this->catalog->part($best[$part['slot']])['tier']) {
                $best[$part['slot']] = $slug;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, string>  $best
     * @return array<string, array{slug: string, rarity: int, upgrade: int}>
     */
    private function mounted(array $best, int $rarity, int $upgrade): array
    {
        return array_map(static fn (string $slug): array => ['slug' => $slug, 'rarity' => $rarity, 'upgrade' => $upgrade], $best);
    }
}
