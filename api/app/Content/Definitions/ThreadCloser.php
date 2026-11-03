<?php

declare(strict_types=1);

namespace App\Content\Definitions;

/**
 * Makes the sections of a circuit into a loop that meets itself: the corners turn the car a full circle and the line ends where it began. Three numbers are moved and
 * nothing else: the angle of the last corner, so that the corners add up to a full turn, and the lengths of two straights, so that the line comes back to the start
 * (once the heading is settled this is a linear problem with one answer for a pair of straights that do not run the same way; two straights that run the same line,
 * the sides of an oval, can only make up for a difference along that line, and they meet half way). Of all the pairs of straights whose answer is a length a straight
 * can have, the pair that is changed the least is taken.
 *
 * The sections are the lines of the form of the thread (see `ThreadForm`); the geometry is the one of `ThreadPath`, which draws them.
 */
final class ThreadCloser
{
    private const MIN_STRAIGHT_M = 5;

    private const MAX_STRAIGHT_M = 5000;

    private const MIN_ANGLE_DEG = 5.0;

    private const MAX_ANGLE_DEG = 360.0;

    /** Two straights that run within this much of the same line (the sine of the angle between them) cannot move the end of the loop in two directions, and the answer for them is not steady. */
    private const PARALLEL = 0.15;

    /** Straights that run the same line to this (as the sine of the angle, which is rounding when the headings are sums of angles) are exactly parallel. */
    private const EXACT = 1e-6;

    /** What may be left across the line of two parallel straights, in metres: they cannot move the end of the loop that way. */
    private const ACROSS_M = 0.75;

    /**
     * @param  list<array<string, mixed>>  $sections  the lines of the form
     * @return array{sections: list<array<string, mixed>>, gap_m: float, moved: array{straights: array{0: int, 1: int}, corner: int}} the sections as a loop, how far the line is from meeting itself after the lengths are made whole metres, and which sections (counted from 1) were moved
     *
     * @throws ThreadCannotClose
     */
    public static function close(array $sections): array
    {
        $sections = array_values($sections);
        $parsed = self::parsed($sections);
        $last = self::lastCorner($parsed);

        // The corners must add up to a full circle, to one side or the other: the last corner takes what the others leave.
        $others = 0.0;

        foreach ($parsed as $index => $section) {
            if ($section['type'] === 'corner' && $index !== $last) {
                $others += $section['side'] * $section['angle'];
            }
        }

        $angle = self::lastAngle($parsed[$last], $others);
        $parsed[$last]['angle'] = $angle;
        $moved = self::bestPair($parsed);

        foreach ($moved['lengths'] as $index => $length) {
            $parsed[$index]['length'] = $length;
        }

        $made = $sections;
        $made[$last]['angle_deg'] = self::tidy($angle);

        foreach ($moved['lengths'] as $index => $length) {
            $made[$index]['length_m'] = (int) $length;
        }

        $end = self::end($parsed);

        return [
            'sections' => $made,
            'gap_m' => hypot($end['x'], $end['y']),
            'moved' => ['straights' => [array_key_first($moved['lengths']) + 1, array_key_last($moved['lengths']) + 1], 'corner' => $last + 1],
        ];
    }

    /**
     * The sections as numbers: a straight has its length, a corner its radius, angle (degrees) and side (left is positive, as in `ThreadPath`).
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array{type: string, length: float, radius: float, angle: float, side: float}>
     */
    private static function parsed(array $sections): array
    {
        $parsed = [];

        foreach ($sections as $line) {
            $type = $line['type'] ?? null;

            if ($type === 'straight' && is_numeric($line['length_m'] ?? null)) {
                $parsed[] = ['type' => 'straight', 'length' => (float) $line['length_m'], 'radius' => 0.0, 'angle' => 0.0, 'side' => 0.0];
            } elseif ($type === 'corner' && is_numeric($line['radius_m'] ?? null) && is_numeric($line['angle_deg'] ?? null)) {
                $parsed[] = ['type' => 'corner', 'length' => 0.0, 'radius' => (float) $line['radius_m'], 'angle' => (float) $line['angle_deg'], 'side' => ($line['turn'] ?? 'right') === 'left' ? 1.0 : -1.0];
            } else {
                throw new ThreadCannotClose('incomplete');
            }
        }

        return $parsed;
    }

    /**
     * @param  list<array{type: string, length: float, radius: float, angle: float, side: float}>  $parsed
     */
    private static function lastCorner(array $parsed): int
    {
        $corners = array_keys(array_filter($parsed, static fn (array $section): bool => $section['type'] === 'corner'));

        if ($corners === []) {
            throw new ThreadCannotClose('no_corner');
        }

        if (count($parsed) - count($corners) < 2) {
            throw new ThreadCannotClose('few_straights');
        }

        return $corners[array_key_last($corners)];
    }

    /**
     * The angle the last corner needs to bring the turn to a full circle, in degrees: of the two ways round, the one a corner can have that is nearer to what it is.
     *
     * @param  array{type: string, length: float, radius: float, angle: float, side: float}  $corner
     */
    private static function lastAngle(array $corner, float $others): float
    {
        $options = [];

        foreach ([360.0, -360.0] as $target) {
            $angle = round(($target - $others) * $corner['side'], 1);

            if ($angle >= self::MIN_ANGLE_DEG && $angle <= self::MAX_ANGLE_DEG) {
                $options[] = $angle;
            }
        }

        if ($options === []) {
            throw new ThreadCannotClose('turn', ['angle' => self::tidy(round((360.0 - $others) * $corner['side'], 1))]);
        }

        usort($options, static fn (float $a, float $b): int => abs($a - $corner['angle']) <=> abs($b - $corner['angle']));

        return $options[0];
    }

    /**
     * The two straights to move, with the lengths that close the line (whole metres).
     *
     * @param  list<array{type: string, length: float, radius: float, angle: float, side: float}>  $parsed
     * @return array{lengths: array<int, float>}
     */
    private static function bestPair(array $parsed): array
    {
        $headings = self::headings($parsed);
        $straights = array_keys(array_filter($parsed, static fn (array $section): bool => $section['type'] === 'straight'));
        $best = null;

        foreach ($straights as $i => $a) {
            foreach (array_slice($straights, $i + 1) as $b) {
                $found = self::solve($parsed, $headings, $a, $b);

                if ($found !== null && ($best === null || $found['cost'] < $best['cost'])) {
                    $best = $found;
                }
            }
        }

        if ($best === null) {
            throw new ThreadCannotClose('lengths', ['min' => self::MIN_STRAIGHT_M, 'max' => self::MAX_STRAIGHT_M]);
        }

        return ['lengths' => $best['lengths']];
    }

    /**
     * The lengths of two straights that bring the end of the line back to the start, or null when they run too nearly the same way or the answer is not a length a
     * straight can have.
     *
     * @param  list<array{type: string, length: float, radius: float, angle: float, side: float}>  $parsed
     * @param  list<float>  $headings  the heading at the start of every section, in radians
     * @return array{lengths: array<int, float>, cost: float}|null
     */
    private static function solve(array $parsed, array $headings, int $a, int $b): ?array
    {
        $da = [cos($headings[$a]), sin($headings[$a])];
        $db = [cos($headings[$b]), sin($headings[$b])];
        $det = $da[0] * $db[1] - $da[1] * $db[0];

        // What the other sections move the end by: the answer moves it back by the same.
        $rest = self::end($parsed, [$a, $b]);

        if (abs($det) < self::EXACT) {
            return self::alongOneLine($parsed, $a, $b, $da, $db, $rest);
        }

        if (abs($det) < self::PARALLEL) {
            return null;
        }

        $lengthA = (-$rest['x'] * $db[1] + $rest['y'] * $db[0]) / $det;
        $lengthB = ($rest['x'] * $da[1] - $rest['y'] * $da[0]) / $det;
        $lengths = [$a => (float) round($lengthA), $b => (float) round($lengthB)];

        foreach ($lengths as $length) {
            if ($length < self::MIN_STRAIGHT_M || $length > self::MAX_STRAIGHT_M) {
                return null;
            }
        }

        return ['lengths' => $lengths, 'cost' => abs($lengths[$a] - $parsed[$a]['length']) + abs($lengths[$b] - $parsed[$b]['length'])];
    }

    /**
     * The lengths of two straights that run the same line (one way or the other) that make up for what the rest leaves along it, meeting half way, or one of them
     * alone when the half does not fit; null when the rest leaves something across the line, which they cannot reach.
     *
     * @param  list<array{type: string, length: float, radius: float, angle: float, side: float}>  $parsed
     * @param  array{0: float, 1: float}  $da
     * @param  array{0: float, 1: float}  $db
     * @param  array{x: float, y: float}  $rest
     * @return array{lengths: array<int, float>, cost: float}|null
     */
    private static function alongOneLine(array $parsed, int $a, int $b, array $da, array $db, array $rest): ?array
    {
        if (abs($rest['x'] * $da[1] - $rest['y'] * $da[0]) > self::ACROSS_M) {
            return null;
        }

        // Both run the same line: the length of one plus (or minus, when they run opposite ways) the length of the other has to be this.
        $sign = $da[0] * $db[0] + $da[1] * $db[1] > 0 ? 1.0 : -1.0;
        $total = -($rest['x'] * $da[0] + $rest['y'] * $da[1]);
        $change = $total - ($parsed[$a]['length'] + $sign * $parsed[$b]['length']);
        $splitA = round($parsed[$a]['length'] + $change / 2);
        $candidates = [
            [$splitA, round($sign * ($total - $splitA))],
            [round($total - $sign * $parsed[$b]['length']), $parsed[$b]['length']],
            [$parsed[$a]['length'], round($sign * ($total - $parsed[$a]['length']))],
        ];
        $best = null;

        foreach ($candidates as [$lengthA, $lengthB]) {
            if (min($lengthA, $lengthB) < self::MIN_STRAIGHT_M || max($lengthA, $lengthB) > self::MAX_STRAIGHT_M) {
                continue;
            }

            $cost = abs($lengthA - $parsed[$a]['length']) + abs($lengthB - $parsed[$b]['length']);

            if ($best === null || $cost < $best['cost']) {
                $best = ['lengths' => [$a => $lengthA, $b => $lengthB], 'cost' => $cost];
            }
        }

        return $best;
    }

    /**
     * The heading at the start of every section, in radians.
     *
     * @param  list<array{type: string, length: float, radius: float, angle: float, side: float}>  $parsed
     * @return list<float>
     */
    private static function headings(array $parsed): array
    {
        $heading = 0.0;
        $headings = [];

        foreach ($parsed as $section) {
            $headings[] = $heading;
            $heading += $section['side'] * deg2rad($section['angle']);
        }

        return $headings;
    }

    /**
     * Where the line ends when it is drawn from the origin, as `ThreadPath` draws it, leaving out the length of the straights in `$without` (to know what the others do).
     *
     * @param  list<array{type: string, length: float, radius: float, angle: float, side: float}>  $parsed
     * @param  list<int>  $without
     * @return array{x: float, y: float}
     */
    private static function end(array $parsed, array $without = []): array
    {
        $x = 0.0;
        $y = 0.0;
        $heading = 0.0;

        foreach ($parsed as $index => $section) {
            if ($section['type'] === 'straight') {
                if (! in_array($index, $without, true)) {
                    $x += cos($heading) * $section['length'];
                    $y += sin($heading) * $section['length'];
                }

                continue;
            }

            $angle = deg2rad($section['angle']);
            $side = $section['side'];
            $x += $section['radius'] * (cos($heading - $side * M_PI / 2 + $side * $angle) + cos($heading + $side * M_PI / 2));
            $y += $section['radius'] * (sin($heading - $side * M_PI / 2 + $side * $angle) + sin($heading + $side * M_PI / 2));
            $heading += $side * $angle;
        }

        return ['x' => $x, 'y' => $y];
    }

    /** An angle with no decimal when it has none (90, not 90.0). */
    private static function tidy(float $angle): int|float
    {
        return $angle === floor($angle) ? (int) $angle : $angle;
    }
}
