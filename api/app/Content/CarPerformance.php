<?php

declare(strict_types=1);

namespace App\Content;

use App\Domain\Competition\PerformanceIndex;
use App\Models\Car;
use App\Models\Part;
use InvalidArgumentException;

/**
 * The performance index (PI) and the class of a car, out of the statistics that the pipeline of a run makes (`RunInputsFactory::stats`) and the numbers of the
 * competition in the rules. This is the one place where the floating point statistics of a car become integers (thousandths) for the index, so the index itself
 * (`PerformanceIndex`) is integer arithmetic. The index does not read a lap time, so it does not depend on a track or on how a lap is worked out, and it does
 * not count the overdue service of a car, which comes and goes, but it counts the wear of the parts, which is part of what the parts are worth.
 */
final class CarPerformance
{
    /** @var array<string, int>|null the statistics of the reference car, in thousandths */
    private ?array $reference = null;

    public function __construct(private readonly RunInputsFactory $inputs, private readonly Rules $rules, private readonly Catalog $catalog) {}

    /**
     * The PI of a car of the pack with these parts on it.
     *
     * @param  array<string, string|array{slug: string, rarity: int, upgrade?: int, wear?: int, affixes?: array<string, int>}>  $mounted  the parts on the car, by slot
     */
    public function of(string $carSlug, array $mounted = []): int
    {
        $pi = $this->settings();

        return min($pi['max_pi'], PerformanceIndex::of(self::milli($this->inputs->stats($carSlug, $mounted)->stats), $this->referenceMilli(), $this->weights(), $pi['stat_cap_ppm'], $pi['reference_pi']));
    }

    /** The PI of a car of a driver, as it is now. */
    public function ofCar(Car $car): int
    {
        return $this->of($car->vehicle_slug, $car->mountedParts());
    }

    /**
     * What a car shows: its PI and the class the PI is in.
     *
     * @param  array<string, string|array{slug: string, rarity: int, upgrade?: int, wear?: int, affixes?: array<string, int>}>  $mounted  the parts on the car, by slot
     * @return array{pi: int, class: string}
     */
    public function ratingOf(string $carSlug, array $mounted = []): array
    {
        $pi = $this->of($carSlug, $mounted);

        return ['pi' => $pi, 'class' => $this->classOf($pi)];
    }

    /**
     * What the car would show with this part put on in its slot, in place of the part that is there: the PI and the class, or null when the part has no slot in the game or
     * does not go on the car.
     *
     * @param  array<string, string|array{slug: string, rarity: int, upgrade?: int, wear?: int, affixes?: array<string, int>}>  $mounted  the parts on the car now, by slot
     * @return array{pi: int, class: string}|null
     */
    public function ratingWith(string $carSlug, array $mounted, Part $part): ?array
    {
        $slot = $this->catalog->part($part->part_slug)['slot'] ?? null;

        if ($slot === null) {
            return null;
        }

        try {
            return $this->ratingOf($carSlug, [...$mounted, $slot => ['slug' => $part->part_slug, 'rarity' => (int) $part->rarity, 'upgrade' => (int) $part->upgrade_level, 'wear' => (int) $part->wear_ppb, 'affixes' => $part->affixes ?? [], 'tuning' => $part->tuning ?? []]]);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * What the car would show with a part of the shop put on its slot, new (the common rarity, no upgrade, no wear), in place of the part that is there: the PI and the class,
     * or null when the part does not go on the car.
     *
     * @param  array<string, string|array{slug: string, rarity: int, upgrade?: int, wear?: int, affixes?: array<string, int>}>  $mounted  the parts on the car now, by slot
     * @return array{pi: int, class: string}|null
     */
    public function ratingWithModel(string $carSlug, array $mounted, string $partSlug): ?array
    {
        $slot = $this->catalog->part($partSlug)['slot'] ?? null;

        if ($slot === null) {
            return null;
        }

        try {
            return $this->ratingOf($carSlug, [...$mounted, $slot => ['slug' => $partSlug, 'rarity' => 1, 'upgrade' => 0, 'wear' => 0]]);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** The class that a PI is in. */
    public function classOf(int $pi): string
    {
        return $this->rules->performanceClasses()->of($pi);
    }

    /**
     * The PI that every statistic at the ceiling of the game would give: the most a car could ever be worth, which the classes have to reach (a limit above it would be
     * a class that no car can be in).
     */
    public function ceiling(): int
    {
        $pi = $this->settings();

        return PerformanceIndex::of(self::milli($this->rules->statCeilings()), $this->referenceMilli(), $this->weights(), $pi['stat_cap_ppm'], $pi['reference_pi']);
    }

    /** The highest PI that is shown (999 in the first game): a car is never worth more. */
    public function maxPi(): int
    {
        return $this->settings()['max_pi'];
    }

    /**
     * The weights of the statistics that the cars of this game have: a game whose model has fewer statistics than the weights name counts the ones it has (the PI of the
     * reference car is the reference PI all the same, because the index is a weighted mean).
     *
     * @return array<string, int>
     */
    private function weights(): array
    {
        $weights = array_intersect_key($this->settings()['weights_ppm'], $this->referenceMilli());

        if (array_sum($weights) < 1) {
            throw new InvalidArgumentException('None of the statistics that the PI is weighed by is a statistic of the cars of this game.');
        }

        return $weights;
    }

    /** @return array<string, int> */
    private function referenceMilli(): array
    {
        return $this->reference ??= self::milli($this->inputs->stats($this->settings()['reference_car'])->stats);
    }

    /** @return array{reference_car: string, reference_pi: int, stat_cap_ppm: int, max_pi: int, weights_ppm: array<string, int>, class_max_pi: array<string, int>, top_class: string} */
    private function settings(): array
    {
        return $this->rules->competition()['pi'] ?? throw new InvalidArgumentException('The rules of this game have no competition, so cars have no performance index.');
    }

    /**
     * @param  array<string, float|int>  $stats
     * @return array<string, int>
     */
    private static function milli(array $stats): array
    {
        return array_map(static fn (float|int $value): int => (int) round($value * 1000), $stats);
    }
}
