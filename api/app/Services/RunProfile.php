<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Domain\Run\LapSimulator;
use App\Domain\Run\ThreadPath;
use App\Models\RaceRun;

/**
 * The picture of the lap of a run: the line of the thread of its track (a circuit is bent until it closes), the speed of its car at every point of the line, and
 * how far through the lap time the car is at each of them. The screen moves a car along it as a function of the time of the lap, so nothing is computed on the
 * server while the car drives: this is made when the screen asks, from the car as it is now and the weather it asks for, in less than a millisecond.
 *
 * The times are fractions of the lap of the model, so the screen spreads them over the lap that was frozen and the car is where the settlement says it is, to the
 * millisecond, even when the model has moved since the run was frozen.
 */
final class RunProfile
{
    /** About this many points make a line: more is not seen, and a long track would send thousands. */
    private const POINTS = 180;

    private const MIN_STEP_M = 10.0;

    public function __construct(private readonly RunInputsFactory $inputs, private readonly Rules $rules) {}

    /**
     * @return array{track: string, lap_from: string, mode: string, length_m: int, weather: string, lap_s: float, drive_fraction: float, sections: list<array<string, mixed>>, points: list<array{0: float, 1: float}>, distance_m: list<float>, speed_kmh: list<float>, time: list<float>}|null null for a track that has no thread
     */
    public function of(RaceRun $run, string $weather): ?array
    {
        $lap = $this->inputs->threadForRun($run);

        if ($lap === null) {
            return null;
        }

        $result = (new LapSimulator($lap->dynamics))->simulate($lap->thread, $lap->carsByWeather[$weather] ?? $lap->carsByWeather['dry']);
        $length = $lap->thread->lengthM();
        $step = max(self::MIN_STEP_M, $length / self::POINTS);
        $outline = ThreadPath::outline($lap->thread, $step);
        $samples = $result->samples($step);
        $aligned = count($samples) === count($outline['points']);
        $total = $aligned ? max(1e-9, $samples[array_key_last($samples)]['elapsed_s']) : 1.0;
        $points = [];
        $speeds = [];
        $times = [];
        $distances = [];

        foreach ($outline['points'] as $index => $point) {
            $points[] = [round($point['x'], 1), round($point['y'], 1)];
            $distances[] = round($point['distance_m'], 1);
            $speeds[] = $aligned ? round($samples[$index]['speed_kmh'], 1) : 0.0;
            $times[] = $aligned ? round($samples[$index]['elapsed_s'] / $total, 5) : round($point['distance_m'] / max(1e-9, $length), 5);
        }

        $sections = [];
        $from = 0.0;

        foreach ($lap->thread->sections as $section) {
            $sections[] = array_filter([
                'type' => $section->type,
                'from_m' => round($from, 1),
                'to_m' => round($from + $section->lengthM, 1),
                'radius_m' => $section->isCorner() ? $section->radiusM : null,
                'angle_deg' => $section->isCorner() ? $section->angleDeg : null,
                'turn' => $section->isCorner() ? $section->turn : null,
            ], static fn (mixed $value): bool => $value !== null);
            $from += $section->lengthM;
        }

        $seconds = $result->seconds();

        return [
            'track' => $run->track_slug,
            // Where the lap time of the track comes from: when it is the formula, the speeds of the picture are those of the model and the screen says so.
            'lap_from' => $this->rules->lapFrom($run->track_slug),
            'mode' => $lap->thread->mode,
            'length_m' => (int) round($length),
            'weather' => isset($lap->carsByWeather[$weather]) ? $weather : 'dry',
            'lap_s' => round($seconds, 3),
            // A run spends the end of every lap on the way back to the start: the car is at the finish while it does.
            'drive_fraction' => round(($seconds - $result->returnSeconds) / max(1e-9, $seconds), 5),
            'sections' => $sections,
            'points' => $points,
            'distance_m' => $distances,
            'speed_kmh' => $speeds,
            'time' => $times,
        ];
    }
}
