<?php

declare(strict_types=1);

namespace App\Content;

use App\Domain\Run\Dynamics;
use App\Domain\Run\Section;
use App\Domain\Run\Thread;
use InvalidArgumentException;

/**
 * The thread of a track as the document writes it (`tracks.<slug>.thread`) and as the game drives it (`Thread`), and what is wrong with a document in words an
 * operator can act on. The schema says what a number may be; this says what the sections come to together: the length of the lap, what a circuit turns, what
 * the lap on the thread needs of the track.
 */
final class ThreadDocument
{
    /** The stats a car of the reference of the track must have for the lap to be driven on the thread (the clearance is the only one that may be left out). */
    public const REFERENCE_STATS = ['accel', 'top_speed', 'grip', 'handling', 'braking'];

    /** How far from a full turn the corners of a circuit may add up to before the operator is told (degrees). */
    public const TURN_TOLERANCE_DEG = 15.0;

    /** What a reference car without a clearance in the document is given. */
    public const DEFAULT_REFERENCE_CLEARANCE = 20.0;

    /**
     * The thread of a track.
     *
     * @param  array<string, mixed>  $document  `tracks.<slug>.thread`
     * @param  string  $trackSurface  what the track is made of (`asphalt` or `gravel`): the surface of the sections that name none
     *
     * @throws InvalidArgumentException when the document is not a thread (the validator says where in words; this refuses to drive one)
     */
    public static function thread(array $document, string $trackSurface): Thread
    {
        $sections = [];

        foreach (array_values((array) ($document['sections'] ?? [])) as $index => $section) {
            $sections[] = self::section((array) $section, $index + 1);
        }

        return new Thread((string) ($document['mode'] ?? ''), $sections, ((int) ($document['return_ms'] ?? 0)) / 1000, $trackSurface);
    }

    /**
     * The length of the lap of a thread in whole metres, as the track says it: the lengths of the sections, each rounded to a metre (a corner is its arc).
     *
     * @param  array<string, mixed>  $document
     */
    public static function lengthM(array $document, string $trackSurface = 'asphalt'): int
    {
        return array_sum(array_map(static fn (Section $section): int => (int) round($section->lengthM), self::thread($document, $trackSurface)->sections));
    }

    /**
     * The constants of the dynamics of the rules (`dynamics`, in ppm) as the model counts them, or its own defaults for rules that say nothing.
     *
     * @param  array<string, mixed>|null  $document
     */
    public static function dynamics(?array $document): Dynamics
    {
        if ($document === null || $document === []) {
            return new Dynamics;
        }

        $ppm = static fn (mixed $value): float => ((int) $value) / 1_000_000;
        $topSpeed = (new Dynamics)->topSpeedOnSurface;

        foreach ((array) ($document['top_speed_ppm'] ?? []) as $surface => $value) {
            $topSpeed[(string) $surface] = $ppm($value);
        }

        return new Dynamics(
            power: $ppm($document['power_ppm'] ?? 600_000),
            lateralG: $ppm($document['lateral_g_ppm'] ?? 950_000),
            brakeG: $ppm($document['brake_g_ppm'] ?? 1_000_000),
            launchG: $ppm($document['launch_g_ppm'] ?? 950_000),
            agilityMin: $ppm($document['agility']['min_ppm'] ?? 250_000),
            agilityMax: $ppm($document['agility']['max_ppm'] ?? 750_000),
            agilityRadiusM: (float) ($document['agility']['radius_m'] ?? 190),
            topSpeedOnSurface: $topSpeed,
            roughRefKmh: (float) ($document['rough']['speed_kmh'] ?? 90),
            roughExponent: $ppm($document['rough']['exponent_ppm'] ?? 500_000),
        );
    }

    /**
     * What is wrong with the thread of a track, in words, as the pointer into the document, the message and whether it stops the track from being used or only
     * says something. A track without a thread has nothing wrong with it unless it wants a lap on one.
     *
     * @param  array<string, mixed>  $track  `tracks.<slug>`
     * @return list<array{pointer: string, message: string, error: bool}>
     */
    public static function problems(array $track): array
    {
        $found = [];
        $lapFrom = (string) ($track['lap_from'] ?? 'stats');
        $thread = $track['thread'] ?? null;

        if (! is_array($thread)) {
            return $lapFrom === 'thread' ? [['pointer' => '/lap_from', 'message' => 'The lap is to be driven on the thread of the track, and the track has none.', 'error' => true]] : [];
        }

        $sections = array_values((array) ($thread['sections'] ?? []));
        $mistake = static function (string $pointer, string $message) use (&$found): void {
            $found[] = ['pointer' => $pointer, 'message' => $message, 'error' => true];
        };
        $note = static function (string $pointer, string $message) use (&$found): void {
            $found[] = ['pointer' => $pointer, 'message' => $message, 'error' => false];
        };

        foreach ($sections as $index => $section) {
            $at = '/thread/sections/'.$index;
            $number = $index + 1;

            if (($section['type'] ?? null) === 'straight' && ! isset($section['length_m'])) {
                $mistake($at, "Section {$number} is a straight and has no length.");
            }

            if (($section['type'] ?? null) === 'corner' && (! isset($section['radius_m']) || ! isset($section['angle_deg']))) {
                $mistake($at, "Section {$number} is a corner and needs a radius and an angle.");
            }
        }

        if ($found !== []) {
            return $found;
        }

        $surface = (string) ($track['surface'] ?? 'asphalt');
        $built = self::thread($thread, $surface);
        $length = self::lengthM($thread, $surface);
        $said = $track['length_m'] ?? null;

        if (is_int($said) && abs($length - $said) > 1) {
            $mistake('/thread/sections', "The sections add up to {$length} m, and the length of the track says {$said} m: they have to be the same (to a metre).");
        }

        $corners = array_filter($built->sections, static fn (Section $section): bool => $section->isCorner());

        if ($built->isCircuit()) {
            if ($corners === []) {
                $note('/thread/sections', 'A circuit with no corner is driven at the top speed all the way: the car with the most of it wins everywhere.');
            }

            $turn = array_sum(array_map(static fn (Section $section): float => ($section->turn === Section::LEFT ? -1 : 1) * $section->angleDeg, $corners));

            if ($corners !== [] && abs(abs($turn) - 360.0) > self::TURN_TOLERANCE_DEG) {
                $note('/thread/sections', 'The corners turn the car '.round(abs($turn)).'° in all, and a closed loop turns it 360°: the line drawn will not meet itself.');
            }

            if ((int) ($thread['return_ms'] ?? 0) > 0) {
                $note('/thread/return_ms', 'A circuit has no way back, so the return time is not counted.');
            }
        }

        if ($lapFrom === 'thread') {
            $missing = array_values(array_filter(self::REFERENCE_STATS, static fn (string $stat): bool => ! isset($track['reference'][$stat])));

            if ($missing !== []) {
                $mistake('/reference', 'The lap is driven on the thread, so the reference car of the track needs '.implode(', ', $missing).' as well.');
            }
        }

        return $found;
    }

    /** @param  array<string, mixed>  $section */
    private static function section(array $section, int $number): Section
    {
        $surface = isset($section['surface']) ? (string) $section['surface'] : null;
        $limit = isset($section['limit_kmh']) ? (float) $section['limit_kmh'] : null;
        $rough = (float) ($section['rough'] ?? 0);

        return match ($section['type'] ?? null) {
            'straight' => Section::straight((float) ($section['length_m'] ?? 0), $surface, $limit, $rough),
            'corner' => Section::corner((float) ($section['radius_m'] ?? 0), (float) ($section['angle_deg'] ?? 0), (string) ($section['turn'] ?? Section::RIGHT), $surface, $limit, $rough),
            default => throw new InvalidArgumentException("Section {$number} is neither a straight nor a corner."),
        };
    }
}
