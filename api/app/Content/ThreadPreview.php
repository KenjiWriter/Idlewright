<?php

declare(strict_types=1);

namespace App\Content;

use App\Domain\Run\LapResult;
use App\Domain\Run\LapSimulator;
use App\Domain\Run\RunFreezer;
use App\Domain\Run\ThreadPath;
use App\Domain\Run\TrackModel;
use InvalidArgumentException;
use OverflowException;

/**
 * What the thread of a track comes to, as numbers and a line that a person is shown while it is being drawn (the page of the thread): the length, how far the line is
 * from closing, how long the reference car of the track takes on it, and, for cars of the catalog, their lap on the thread next to their lap by the formula of the
 * demands, so that a track can be moved from one to the other with the differences in front of the eyes.
 *
 * It plays the real thing: the rules of the draft, the factory of the runs and the freezer, once with the lap driven on the thread and once by the formula.
 */
final class ThreadPreview
{
    public function __construct(private readonly Catalog $catalog) {}

    /**
     * @param  array<string, mixed>  $rules  the document of the rules, as it is being worked on
     * @param  list<string>  $cars  the slugs of the cars to drive (only the ones that burn fuel can run)
     * @return array{length_m: int, sections: int, gap_m: float, turn_deg: float, base_lap_ms: int|null, reference_s: float, peak_kmh: float, reference_split: array<string, float>, pace: float|null, outline: list<array{x: float, y: float, distance_m: float, section: int, speed_kmh?: float}>, cars: list<array{slug: string, name: array<string, string>, tier: int, formula_ms: int, thread_ms: int, delta_percent: float, physical_s: float, average_kmh: float, peak_kmh: float, split: array<string, float>}>}|null null when the track has no thread that can be driven
     */
    public function of(array $rules, string $trackSlug, array $cars): ?array
    {
        /** @var array<string, mixed> $rules */
        $rules = json_decode((string) json_encode($rules), true);
        $track = $rules['tracks'][$trackSlug] ?? null;

        if (! is_array($track) || ! isset($track['thread']) || ! is_array($track['thread'])) {
            return null;
        }

        try {
            $surface = (string) ($track['surface'] ?? 'asphalt');
            $thread = ThreadDocument::thread($track['thread'], $surface);
            $length = ThreadDocument::lengthM($track['thread'], $surface);
            // The line is drawn as it is, open where the sections do not close it, so that the operator sees the gap to close.
            $outline = ThreadPath::outline($thread, close: false);
            $drivable = array_values(array_filter($cars, fn (string $slug): bool => ($this->catalog->vehicle($slug)['powertrain'] ?? null) === 'ice'));

            $threadRules = $this->with($rules, $trackSlug, 'thread', $length);
            $run = $this->referenceRun($threadRules, $trackSlug, $drivable[0] ?? $this->anyCar());
            $reference = $run->seconds();
            $samples = $run->samples();
            $points = $outline['points'];

            // The line is drawn from the same steps as the picture of the lap, so the speed of the reference car belongs to each point of it.
            if (count($samples) === count($points)) {
                foreach ($points as $index => $point) {
                    $points[$index]['speed_kmh'] = $samples[$index]['speed_kmh'];
                }
            }
            $base = $this->baseLapMs($track, $length);
            $said = $base ?? (int) round($reference * 1000);
            $onThread = $this->with($rules, $trackSlug, 'thread', $length, $said);
            $byFormula = $this->with($rules, $trackSlug, 'stats', $length, $said);
            $rows = array_map(fn (string $slug): array => $this->row($slug, $onThread, $byFormula, $trackSlug), $drivable);
        } catch (InvalidArgumentException|OverflowException) {
            return null;
        }

        return [
            'length_m' => $length,
            'sections' => count($thread->sections),
            'gap_m' => $outline['gap_m'],
            'turn_deg' => $outline['turn_deg'],
            'base_lap_ms' => $base,
            'reference_s' => $reference,
            'pace' => $base === null || $reference <= 0 ? null : $base / 1000 / $reference,
            'outline' => $points,
            'peak_kmh' => $run->peakKmh(),
            'reference_split' => $run->split(),
            'cars' => $rows,
        ];
    }

    /**
     * The speed that the reference car of the track does on the thread, in km/h with four decimals, for the track to say it: what a thread without a speed comes to.
     *
     * @param  array<string, mixed>  $rules
     */
    public function speedKmh(array $rules, string $trackSlug): ?float
    {
        $preview = $this->of($rules, $trackSlug, []);

        return $preview === null || $preview['reference_s'] <= 0 ? null : round($preview['length_m'] * 3.6 / $preview['reference_s'], 4);
    }

    /**
     * The rules with the track driven on its thread or by the formula, with the length the sections add up to, and a base lap (the lap that is said, or the one given).
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function with(array $rules, string $trackSlug, string $lapFrom, int $length, ?int $base = null): array
    {
        $rules['tracks'][$trackSlug]['lap_from'] = $lapFrom;
        $rules['tracks'][$trackSlug]['length_m'] = $length;

        if (! isset($rules['tracks'][$trackSlug]['base_lap_ms']) && ! isset($rules['tracks'][$trackSlug]['speed_kmh'])) {
            $rules['tracks'][$trackSlug]['base_lap_ms'] = $base ?? 60_000;
        }

        return $rules;
    }

    /** The base lap that the track says, by hand or by its speed over the length of the sections, or null when it says none. */
    private function baseLapMs(array $track, int $length): ?int
    {
        if (isset($track['base_lap_ms'])) {
            return (int) $track['base_lap_ms'];
        }

        return isset($track['speed_kmh']) && is_numeric($track['speed_kmh']) && $track['speed_kmh'] > 0 ? TrackModel::lapMs($length, TrackNumbers::speedScaled($track['speed_kmh'])) : null;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function referenceRun(array $rules, string $trackSlug, string $car): LapResult
    {
        $inputs = (new RunInputsFactory($this->catalog, Rules::fromArray($rules)))->make($car, $trackSlug);
        $lap = $inputs->thread ?? throw new InvalidArgumentException('The track is not driven on a thread.');

        return (new LapSimulator($lap->dynamics))->simulate($lap->thread, $lap->reference);
    }

    /**
     * @param  array<string, mixed>  $onThread
     * @param  array<string, mixed>  $byFormula
     * @return array{slug: string, name: array<string, string>, tier: int, formula_ms: int, thread_ms: int, delta_percent: float, physical_s: float, average_kmh: float, peak_kmh: float, split: array<string, float>}
     */
    private function row(string $slug, array $onThread, array $byFormula, string $trackSlug): array
    {
        $inputs = (new RunInputsFactory($this->catalog, Rules::fromArray($onThread)))->make($slug, $trackSlug);
        $lap = $inputs->thread ?? throw new InvalidArgumentException('The track is not driven on a thread.');
        $physical = (new LapSimulator($lap->dynamics))->simulate($lap->thread, $lap->carsByWeather['dry']);
        $freezer = new RunFreezer;
        $threadMs = $freezer->freeze($inputs, 'preview', 0)->config['lap_ms']['dry'];
        $formulaMs = $freezer->freeze((new RunInputsFactory($this->catalog, Rules::fromArray($byFormula)))->make($slug, $trackSlug), 'preview', 0)->config['lap_ms']['dry'];
        $vehicle = $this->catalog->vehicle($slug);

        return [
            'slug' => $slug,
            'name' => (array) $vehicle['name'],
            'tier' => (int) $vehicle['tier'],
            'formula_ms' => $formulaMs,
            'thread_ms' => $threadMs,
            'delta_percent' => $formulaMs > 0 ? 100.0 * ($threadMs - $formulaMs) / $formulaMs : 0.0,
            'physical_s' => $physical->seconds(),
            'average_kmh' => $physical->averageKmh(),
            'peak_kmh' => $physical->peakKmh(),
            'split' => $physical->split(),
        ];
    }

    private function anyCar(): string
    {
        foreach ($this->catalog->vehicles() as $slug => $vehicle) {
            if (($vehicle['powertrain'] ?? null) === 'ice') {
                return $slug;
            }
        }

        throw new InvalidArgumentException('No car of the catalog burns fuel.');
    }
}
