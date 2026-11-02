<?php

declare(strict_types=1);

namespace App\Content\Publishing;

use App\Content\BotProfiles;
use App\Content\Catalog;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Domain\Run\Rarity;
use App\Domain\Run\RunFreezer;
use App\Models\ContentDefinition;
use App\Services\ContentPacks;
use InvalidArgumentException;

/**
 * What a set of test cars does on the tracks of the game, on the live content and on the content as it is being worked on (a draft, with unsaved documents laid over
 * it if the form has any), side by side: the lap, the reward, the place among the bots, the laps an hour, what an hour pays, what its fuel costs, the profit after the
 * fuel and the experience of an hour. It is what the panel shows next to a form while a car, a part or a track is being changed, and what `economy:preview` prints.
 *
 * A test car is a car of the content, its level and the parts on it with their rarity and their upgrade. The lap of a car is not worked out here: it is what the run of
 * the game is frozen into for the car on the track (`RunInputsFactory` and `RunFreezer`), so a track that takes its lap from a thread, or a rule that changes how a lap
 * is made, shows in the preview as it does in the game. The bots are those of the pack that is live, because the table of a new pack is only made when the pack is.
 *
 * Nothing is saved and nothing is random: the same cars on the same content give the same answer.
 */
final class TestCarPreview
{
    /** The most test cars one call plays: every car is played on every track, twice. */
    public const MAX_CARS = 8;

    private const HOUR_MS = 3_600_000;

    public function __construct(
        private readonly ContentPacks $packs,
        private readonly Catalog $liveCatalog,
        private readonly Rules $liveRules,
        private readonly RunInputsFactory $live,
    ) {}

    /**
     * The test cars on the live content and on the draft.
     *
     * @param  list<array{label?: string, car: string, level?: int, parts?: array<string, string|array{slug: string, rarity?: int, upgrade?: int}>}>  $cars  the cars to play, up to `MAX_CARS`; `parts` are by the slot of the car, a part is its slug or the slug with its rarity (1 common to 5 legendary) and its upgrade (+N)
     * @param  list<string>|null  $tracks  the tracks to play, by slug, all the tracks of the content when null
     * @param  array<string, string>  $replace  documents that are not saved yet, by key (`parts/x`, `rules/starter`), laid over the draft
     * @return array{
     *     cars: list<array{label: string, car: string, level: int, parts: array<string, array{slug: string, rarity: int, upgrade: int}>}>,
     *     live: array{source: array{kind: string, pack: string|null}, cars: list<array<string, mixed>>},
     *     draft: array{source: array{kind: string, pack: string|null}, cars: list<array<string, mixed>>}|null,
     *     diff: list<array<string, array<string, array{live: int|null, draft: int|null, delta: int|null}>>>
     * }
     *
     * @throws InvalidArgumentException when the cars are not made as above or there are too many of them
     */
    public function preview(array $cars, ?array $tracks = null, array $replace = []): array
    {
        $cars = $this->normalise($cars);
        $live = $this->side($cars, $tracks, $this->liveCatalog, $this->liveRules, $this->live, ['kind' => 'live', 'pack' => $this->packs->active()?->number]);
        $draft = $this->draftSide($replace);
        $draftResult = $draft === null ? null : $this->side($cars, $tracks, $draft['catalog'], $draft['rules'], $draft['factory'], ['kind' => 'draft', 'pack' => null]);

        return [
            'cars' => $cars,
            'live' => $live,
            'draft' => $draftResult,
            'diff' => $draftResult === null ? [] : $this->diff($live, $draftResult),
        ];
    }

    /**
     * The test cars made what they need to be: a car, a level of at least 1 and the parts by slot with their rarity (1 to 5) and upgrade (0 or more).
     *
     * @param  list<array<string, mixed>>  $cars
     * @return list<array{label: string, car: string, level: int, parts: array<string, array{slug: string, rarity: int, upgrade: int}>}>
     */
    private function normalise(array $cars): array
    {
        if ($cars === [] || count($cars) > self::MAX_CARS) {
            throw new InvalidArgumentException('Give between 1 and '.self::MAX_CARS.' test cars.');
        }

        $made = [];

        foreach (array_values($cars) as $index => $car) {
            if (! is_array($car) || ! is_string($car['car'] ?? null) || $car['car'] === '') {
                throw new InvalidArgumentException('Test car '.($index + 1).' has no car.');
            }

            $parts = [];

            foreach ((array) ($car['parts'] ?? []) as $slot => $part) {
                $entry = is_string($part) ? ['slug' => $part] : (array) $part;

                if (! is_string($slot) || ! is_string($entry['slug'] ?? null) || $entry['slug'] === '') {
                    throw new InvalidArgumentException('Test car '.($index + 1).' has a part with no slot or no slug.');
                }

                $parts[$slot] = [
                    'slug' => $entry['slug'],
                    'rarity' => max(1, min(5, (int) ($entry['rarity'] ?? 1))),
                    'upgrade' => max(0, (int) ($entry['upgrade'] ?? 0)),
                ];
            }

            $made[] = ['label' => (string) ($car['label'] ?? $car['car']), 'car' => $car['car'], 'level' => max(1, (int) ($car['level'] ?? 1)), 'parts' => $parts];
        }

        return $made;
    }

    /**
     * The draft as a factory of runs, or null when the game has no content in the database to work on (it reads its files).
     *
     * @param  array<string, string>  $replace
     * @return array{catalog: Catalog, rules: Rules, factory: RunInputsFactory}|null
     */
    private function draftSide(array $replace): ?array
    {
        if (! ContentDefinition::query()->exists() && $replace === []) {
            return null;
        }

        $working = $this->packs->working($replace);
        $catalog = Catalog::fromSource($working);
        $rules = Rules::fromSource($working);

        return ['catalog' => $catalog, 'rules' => $rules, 'factory' => new RunInputsFactory($catalog, $rules, BotProfiles::fromSource($this->packs->activeSource() ?? $working))];
    }

    /**
     * Every car on every track of one side.
     *
     * @param  list<array{label: string, car: string, level: int, parts: array<string, array{slug: string, rarity: int, upgrade: int}>}>  $cars
     * @param  list<string>|null  $tracks
     * @param  array{kind: string, pack: string|null}  $source
     * @return array{source: array{kind: string, pack: string|null}, cars: list<array<string, mixed>>}
     */
    private function side(array $cars, ?array $tracks, Catalog $catalog, Rules $rules, RunInputsFactory $factory, array $source): array
    {
        $played = [];

        foreach ($cars as $car) {
            $played[] = $this->play($car, $tracks ?? array_keys($rules->tracks()), $catalog, $rules, $factory);
        }

        return ['source' => $source, 'cars' => $played];
    }

    /**
     * One car: what is wrong with putting these parts on it at this level, its statistics, and what it does on each track.
     *
     * @param  array{label: string, car: string, level: int, parts: array<string, array{slug: string, rarity: int, upgrade: int}>}  $car
     * @param  list<string>  $tracks
     * @return array<string, mixed>
     */
    private function play(array $car, array $tracks, Catalog $catalog, Rules $rules, RunInputsFactory $factory): array
    {
        if ($catalog->vehicle($car['car']) === null) {
            return ['issues' => [['code' => 'unknown_car', 'car' => $car['car']]], 'stats' => null, 'tracks' => []];
        }

        $issues = $this->issues($car, $catalog, $rules);

        try {
            $stats = array_map(static fn (float $value): float => round($value, 1), $factory->stats($car['car'], $car['parts'])->stats);
        } catch (InvalidArgumentException $problem) {
            return ['issues' => [...$issues, ['code' => 'cannot_race', 'message' => $problem->getMessage()]], 'stats' => null, 'tracks' => []];
        }

        $played = [];

        foreach ($tracks as $track) {
            $played[$track] = $this->measure($factory, $rules, $car, $track);
        }

        return ['issues' => $issues, 'stats' => $stats, 'tracks' => $played];
    }

    /**
     * What the game would refuse about these parts on this car (the preview plays them all the same: it answers what the car would do, not whether the garage allows it).
     *
     * @param  array{label: string, car: string, level: int, parts: array<string, array{slug: string, rarity: int, upgrade: int}>}  $car
     * @return list<array<string, mixed>>
     */
    private function issues(array $car, Catalog $catalog, Rules $rules): array
    {
        $issues = [];

        foreach ($car['parts'] as $slot => $mounted) {
            $part = $catalog->part($mounted['slug']);

            if ($part === null) {
                $issues[] = ['code' => 'unknown_part', 'slot' => $slot, 'part' => $mounted['slug']];

                continue;
            }

            if ($part['slot'] !== $slot) {
                $issues[] = ['code' => 'wrong_slot', 'slot' => $slot, 'part' => $mounted['slug'], 'part_slot' => $part['slot']];
            }

            $required = $rules->requiredCarLevel((int) $part['tier']);

            if ($required > $car['level']) {
                $issues[] = ['code' => 'car_level_required', 'slot' => $slot, 'part' => $mounted['slug'], 'required' => $required, 'current' => $car['level']];
            }

            $cap = $rules->upgradeCap(Rarity::from($mounted['rarity']), $car['level']);

            if ($mounted['upgrade'] > $cap) {
                $issues[] = ['code' => 'upgrade_above_cap', 'slot' => $slot, 'part' => $mounted['slug'], 'upgrade' => $mounted['upgrade'], 'cap' => $cap];
            }
        }

        return $issues;
    }

    /**
     * The lap dry and in the rain, the place among the bots and what an hour of dry laps pays and costs, as the run of the game is frozen for the car on the track.
     *
     * @param  array{label: string, car: string, level: int, parts: array<string, array{slug: string, rarity: int, upgrade: int}>}  $car
     * @return array<string, mixed>
     */
    private function measure(RunInputsFactory $factory, Rules $rules, array $car, string $track): array
    {
        if ($rules->track($track) === null) {
            return ['error' => 'unknown_track'];
        }

        try {
            $config = (new RunFreezer)->freeze($factory->make($car['car'], $track, $car['parts']), 'test-car-preview', 0)->config;
        } catch (InvalidArgumentException $problem) {
            return ['error' => $problem->getMessage()];
        }

        $lapMs = $config['lap_ms']['dry'];
        $lapsPerHour = self::HOUR_MS / $lapMs;
        $income = (int) round($config['lap_cash_cents']['dry'] * $lapsPerHour);
        $fuel = (int) round($config['fuel_per_lap_ml'] / 1000 * $rules->fuelPriceCentsPerLiter() * $lapsPerHour);

        return [
            'lap_ms' => ['dry' => $lapMs, 'rain' => $config['lap_ms']['rain'] ?? null],
            'position' => $config['field']['position'] ?? null,
            'field_size' => $config['field']['size'] ?? null,
            'reward_cents_per_lap' => $config['lap_cash_cents']['dry'],
            'xp_per_lap' => $config['lap_xp']['dry'],
            'fuel_per_lap_ml' => $config['fuel_per_lap_ml'],
            'laps_per_hour' => round($lapsPerHour, 2),
            'income_cents_per_hour' => $income,
            'fuel_cents_per_hour' => $fuel,
            'profit_cents_per_hour' => $income - $fuel,
            'xp_per_hour' => (int) round($config['lap_xp']['dry'] * $lapsPerHour),
        ];
    }

    /**
     * What the draft changes against the live content, car by car and track by track (a track that both sides can play): the dry lap, the profit of an hour, the
     * experience of an hour and the place, each as the live number, the draft number and the difference (draft less live; for the place a negative one is a better one).
     *
     * @param  array{source: array<string, mixed>, cars: list<array<string, mixed>>}  $live
     * @param  array{source: array<string, mixed>, cars: list<array<string, mixed>>}  $draft
     * @return list<array<string, array<string, array{live: int|null, draft: int|null, delta: int|null}>>>
     */
    private function diff(array $live, array $draft): array
    {
        $changes = [];

        foreach ($live['cars'] as $index => $car) {
            $changes[$index] = [];

            foreach ($car['tracks'] as $track => $was) {
                $is = $draft['cars'][$index]['tracks'][$track] ?? null;

                if (isset($was['error']) || $is === null || isset($is['error'])) {
                    continue;
                }

                $changes[$index][$track] = [
                    'lap_ms' => $this->change($was['lap_ms']['dry'], $is['lap_ms']['dry']),
                    'profit_cents_per_hour' => $this->change($was['profit_cents_per_hour'], $is['profit_cents_per_hour']),
                    'xp_per_hour' => $this->change($was['xp_per_hour'], $is['xp_per_hour']),
                    'position' => $this->change($was['position'], $is['position']),
                ];
            }
        }

        return $changes;
    }

    /** @return array{live: int|null, draft: int|null, delta: int|null} */
    private function change(?int $was, ?int $is): array
    {
        return ['live' => $was, 'draft' => $is, 'delta' => $was === null || $is === null ? null : $is - $was];
    }
}
