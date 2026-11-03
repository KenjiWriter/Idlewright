<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Content\ThreadDocument;
use App\Domain\Run\LapSimulator;
use App\Domain\Run\ThreadPath;
use App\Models\RaceRun;

/**
 * What the thread of a track says to a driver who is choosing it: a small drawing of the line, where the time of a lap goes for the car that the track is made for
 * (the more of it is spent accelerating, holding the pace, braking or in corners, the more the track asks of that), and what the car that is played does better or
 * worse than that car in each of those, in seconds of the lap of the track. A track that has no thread says nothing.
 *
 * The seconds are the ones of the model, scaled so that the car the track is made for drives exactly the base lap of the track. On a track that is driven on its
 * thread they add up to the difference between the lap of the car and that lap; on a track that is driven by the formula of the demands they say how the car
 * would handle the shape of the track, which is not the lap time the card shows (the screen says so).
 *
 * A track whose reference car lacks one of the statistics that the model drives (`ThreadDocument::REFERENCE_STATS`) has a line and nothing else: there is no car to
 * compare with.
 */
final class TrackCharacter
{
    /** About this many points make the drawing: it is a thumbnail. */
    private const POINTS = 56;

    private const MIN_STEP_M = 10.0;

    /** The groups of the time of a lap, in the order they are told. */
    public const GROUPS = ['accelerating', 'holding', 'braking', 'cornering'];

    /** A track is told as a track of corners from this share of the time of a lap in them, as one that holds the limit from this share, and as one of power from this share in accelerating (in percent). */
    private const TWISTY_FROM = 35;

    private const LIMIT_FROM = 35;

    private const POWER_FROM = 70;

    public function __construct(private readonly Rules $rules, private readonly RunInputsFactory $inputs) {}

    /**
     * @return array{lap_from: string, mode: string, points: list<array{0: int, 1: int}>, character: string|null, shares: array<string, int>|null, car: array<string, float>|null}|null null for a track that has no thread
     */
    public function of(RaceRun $run, string $trackSlug): ?array
    {
        $thread = $this->rules->thread($trackSlug);

        if ($thread === null) {
            return null;
        }

        $track = $this->rules->track($trackSlug) ?? [];
        $step = max(self::MIN_STEP_M, $thread->lengthM() / self::POINTS);
        $points = [];

        foreach (ThreadPath::outline($thread, $step)['points'] as $point) {
            $points[] = [(int) round($point['x']), (int) round($point['y'])];
        }

        $made = ['lap_from' => $this->rules->lapFrom($trackSlug), 'mode' => $thread->mode, 'points' => $points, 'character' => null, 'shares' => null, 'car' => null];
        $lap = $this->inputs->threadForRun($run, $trackSlug);

        if ($lap === null || array_filter(ThreadDocument::REFERENCE_STATS, static fn (string $stat): bool => ! isset($track['reference'][$stat])) !== []) {
            return $made;
        }

        $simulator = new LapSimulator($lap->dynamics);
        $reference = $simulator->simulate($lap->thread, $lap->reference)->split();
        $car = $simulator->simulate($lap->thread, $lap->carsByWeather['dry'])->split();
        $driving = max(1e-9, array_sum(array_intersect_key($reference, array_flip(self::GROUPS))));
        // One second of the model is this many seconds of the lap of the track: the car the track is made for drives the base lap of it.
        $scale = ($track['base_lap_ms'] / 1000) / max(1e-9, $lap->referenceSeconds);

        foreach (self::GROUPS as $group) {
            $made['shares'][$group] = (int) round(100 * $reference[$group] / $driving);
            $made['car'][$group] = round(($car[$group] - $reference[$group]) * $scale, 1);
        }

        $made['character'] = $this->character($made['shares']);

        return $made;
    }

    /**
     * How the track is told: a track that holds the limit most of the lap, one of corners, one of power (the time goes on getting up to speed), or neither.
     *
     * @param  array<string, int>  $shares
     */
    private function character(array $shares): string
    {
        return match (true) {
            $shares['holding'] >= self::LIMIT_FROM => 'limit',
            $shares['cornering'] >= self::TWISTY_FROM => 'twisty',
            $shares['accelerating'] >= self::POWER_FROM => 'power',
            default => 'balanced',
        };
    }
}
