<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Catalog;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Domain\Market\Tuning;
use App\Domain\Run\RunFreezer;
use Throwable;

/**
 * What a full set of the bonuses of the tuning is worth: for a reference car on a track, the best bonuses of the rules that the track can use, each at the most a try can give,
 * in every place of every part of the shop set, against the same set with none. The one place that `tuning:budget`, the gate of the publication and the page of the bonuses in
 * the panel take their numbers from, so that they cannot disagree.
 *
 * The worst case, the track where the set takes the most off the lap, is what is judged. The design asks for no more than `ride.tuning.budget.target_lap_percent` (10%); the owner
 * kept the limits of one bonus (12% with a context, 8% without) knowing the best set goes a little over that, so the gate fails only above the ceiling that was approved
 * (`max_lap_percent`) and says, as a warning, what lies between the two: the case is never passed in silence. The income is reported, not judged: it carries the jump of the
 * place among the bots, and its limit is a matter of the balance (stage B6).
 *
 * The parts are the epic ones of the shop (the tires for the surface of the track), so the numbers are those of the tables of the design.
 */
final class TuningBudget
{
    /** The cars that are the measure: the one of the start of the ladder and the heaviest one, which the bonuses help the most on gravel. */
    public const CARS = ['rdzak-gt-4', 'bizon-m-2'];

    /** The shop set that the bonuses are put on, by slot, and the rarity it is at (epic). */
    private const SET = ['suspension' => 'sport-suspension-t1', 'brakes' => 'disc-brakes-t2', 'exhaust' => 'sport-exhaust-t2', 'gearbox' => 'manual-5-gearbox-t2'];

    private const TIRES = ['asphalt' => 'street-tires-t2', 'gravel' => 'offroad-tires-t2'];

    private const RARITY = 4;

    public function __construct(private readonly Rules $rules, private readonly Catalog $catalog, private readonly RunInputsFactory $factory) {}

    /**
     * The report: a row for every car on every track kind and surface of the game, the worst of them, the checks against the limits and the warnings.
     *
     * @return array<string, mixed>|null null when the game has no bonuses, or lacks the cars and parts of the measure
     */
    public function report(): ?array
    {
        $tuning = $this->rules->tuning();

        if (($tuning['definitions'] ?? []) === [] || ! $this->hasTheSet()) {
            return null;
        }

        $rows = [];

        foreach (self::CARS as $car) {
            foreach ($this->tracks() as $track) {
                try {
                    $rows[] = $this->row($car, $track);
                } catch (Throwable) {
                    // A track that the car cannot drive, or content that the factory does not take, is not a case of the measure.
                    continue;
                }
            }
        }

        if ($rows === []) {
            return null;
        }

        usort($rows, static fn (array $a, array $b): int => $a['lap_percent'] <=> $b['lap_percent']);

        $worst = $rows[0];
        $drop = round(-$worst['lap_percent'], 2);
        $target = (float) config('ride.tuning.budget.target_lap_percent', 10.0);
        $approved = (float) config('ride.tuning.budget.max_lap_percent', 12.0);
        $warnings = [];

        if ($drop > $target && $drop <= $approved) {
            $warnings[] = sprintf('The best set takes %.1f%% off the lap of the %s at %s, over the %.1f%% of the design (the ceiling that was approved is %.1f%%): the limits of one bonus decide how far over.', $drop, $worst['car'], $worst['track'], $target, $approved);
        }

        return [
            'rows' => $rows,
            'worst' => $worst,
            'checks' => [['name' => 'worst lap drop of the best set (%)', 'value' => $drop, 'min' => 0.0, 'max' => $approved, 'ok' => $drop <= $approved]],
            'warnings' => $warnings,
            'target' => $target,
            'approved' => $approved,
        ];
    }

    /** Whether the cars, the tires and the set of the measure are in the content of the game. */
    private function hasTheSet(): bool
    {
        foreach ([...self::CARS] as $car) {
            if ($this->catalog->vehicle($car) === null) {
                return false;
            }
        }

        foreach ([...array_values(self::SET), ...array_values(self::TIRES)] as $part) {
            if ($this->catalog->part($part) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * One track for each kind and surface that the game has: the first of its tracks that is of it.
     *
     * @return list<string>
     */
    private function tracks(): array
    {
        $chosen = [];

        foreach (array_keys($this->rules->regions()) as $region) {
            foreach ($this->rules->tracksOf($region) as $slug) {
                $track = $this->rules->track($slug);

                if ($track !== null) {
                    $chosen[($track['kind'] ?? 'standard').'|'.$track['surface']] ??= $slug;
                }
            }
        }

        return array_values($chosen);
    }

    /**
     * What the best set does for a car on a track: the lap and the income against the set with no bonuses.
     *
     * @return array<string, mixed>
     */
    private function row(string $car, string $trackSlug): array
    {
        $track = $this->rules->track($trackSlug) ?? throw new \InvalidArgumentException("Unknown track {$trackSlug}.");
        $base = $this->set($track['surface']);
        $none = $this->measure($car, $trackSlug, $base);
        $best = $this->measure($car, $trackSlug, $this->withBonuses($car, $trackSlug, $base));

        return [
            'car' => $car,
            'track' => $trackSlug,
            'kind' => $track['kind'] ?? 'standard',
            'surface' => $track['surface'],
            'lap_ms' => (int) round($none['lap_ms']),
            'best_lap_ms' => (int) round($best['lap_ms']),
            'lap_percent' => round(($best['lap_ms'] / $none['lap_ms'] - 1) * 100, 2),
            'income_percent' => round(($best['cash_per_hour'] / $none['cash_per_hour'] - 1) * 100, 2),
        ];
    }

    /**
     * The shop set of a track of that surface, without bonuses.
     *
     * @return array<string, array<string, mixed>>
     */
    private function set(string $surface): array
    {
        $set = ['tires' => ['slug' => self::TIRES[$surface] ?? self::TIRES['asphalt'], 'rarity' => self::RARITY]];

        foreach (self::SET as $slot => $slug) {
            $set[$slot] = ['slug' => $slug, 'rarity' => self::RARITY];
        }

        return $set;
    }

    /**
     * The set with the best bonuses in the places of each part: every bonus that fits the slot and moves the lap is tried alone at the most a try can give, and the ones that
     * take the most off it fill the places, one of each kind to a part.
     *
     * @param  array<string, array<string, mixed>>  $set
     * @return array<string, array<string, mixed>>
     */
    private function withBonuses(string $car, string $trackSlug, array $set): array
    {
        $engine = new Tuning($this->rules->tuning());
        $baseLap = $this->measure($car, $trackSlug, $set)['lap_ms'];
        $places = $engine->slotsFor(null);

        foreach ($set as $slot => $part) {
            $gains = [];

            foreach ($engine->pool($slot, PHP_INT_MAX) as $key => $bonus) {
                // The fuel, the wear and the reliability do not move the lap.
                if (! in_array($bonus['effect'], ['stat', 'surface'], true)) {
                    continue;
                }

                $tried = $set;
                $tried[$slot]['tuning'] = [['key' => $key, 'ppm' => $bonus['max_ppm'], 'tries' => 1, 'fails' => 0]];
                $gain = $baseLap - $this->measure($car, $trackSlug, $tried)['lap_ms'];

                if ($gain > 0) {
                    $gains[$key] = $gain;
                }
            }

            arsort($gains);
            $held = [];

            foreach (array_slice(array_keys($gains), 0, $places) as $key) {
                $held[] = ['key' => $key, 'ppm' => $this->rules->tuning()['definitions'][$key]['max_ppm'], 'tries' => 1, 'fails' => 0];
            }

            if ($held !== []) {
                $set[$slot]['tuning'] = $held;
            }
        }

        return $set;
    }

    /**
     * The mean lap and the income per hour of a car on a track with these parts, over the weather of the region.
     *
     * @param  array<string, array<string, mixed>>  $mounted
     * @return array{lap_ms: float, cash_per_hour: float}
     */
    private function measure(string $car, string $track, array $mounted): array
    {
        $inputs = $this->factory->make($car, $track, $mounted);
        $config = (new RunFreezer)->freeze($inputs, 'tuning-budget', 0)->config;
        $lap = 0.0;
        $cash = 0.0;

        foreach ($inputs->weatherChancesPpm as $weather => $chance) {
            $share = $chance / 1_000_000;
            $lap += $share * $config['lap_ms'][$weather];
            $cash += $share * $config['lap_cash_cents'][$weather] * 3_600_000 / $config['lap_ms'][$weather] / 100;
        }

        return ['lap_ms' => $lap, 'cash_per_hour' => $cash];
    }
}
