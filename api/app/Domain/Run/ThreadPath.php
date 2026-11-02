<?php

declare(strict_types=1);

namespace App\Domain\Run;

/**
 * The line of a thread seen from above: where the car is at every distance along the lap, by drawing the sections one after the other (a straight goes on, a corner
 * is an arc of its radius and angle). It only draws: the lap does not depend on it.
 *
 * The sections of a loop rarely close on paper, so the line of a circuit is bent a little, in proportion to the distance, until it meets itself, and the gap it had
 * is told. The line is in metres, with the start at the origin and the first section going to the right, and y grows upwards (a drawing flips it).
 */
final class ThreadPath
{
    /**
     * The line of the thread: `points` are one every `$stepM` metres or so and the end of every section, `gap_m` is how far the end of the last section was from the start
     * (0 for a run, which does not close), and `turn_deg` is what the corners turn the car in all (left is positive). A circuit is bent until it closes, unless `$close` is
     * false, which draws it as the sections make it.
     *
     * @return array{points: list<array{x: float, y: float, distance_m: float, section: int}>, gap_m: float, turn_deg: float}
     */
    public static function outline(Thread $thread, float $stepM = 10.0, bool $close = true): array
    {
        $x = 0.0;
        $y = 0.0;
        $heading = 0.0;
        $distance = 0.0;
        $turn = 0.0;
        $points = [['x' => 0.0, 'y' => 0.0, 'distance_m' => 0.0, 'section' => 0]];

        foreach ($thread->sections as $index => $section) {
            $steps = max(1, (int) ceil($section->lengthM / max(0.5, $stepM)));

            if (! $section->isCorner()) {
                for ($step = 1; $step <= $steps; $step++) {
                    $along = $section->lengthM * $step / $steps;
                    $points[] = ['x' => $x + cos($heading) * $along, 'y' => $y + sin($heading) * $along, 'distance_m' => $distance + $along, 'section' => $index];
                }

                $x += cos($heading) * $section->lengthM;
                $y += sin($heading) * $section->lengthM;
            } else {
                $side = $section->turn === Section::LEFT ? 1.0 : -1.0;
                $angle = deg2rad($section->angleDeg);
                $centreX = $x + $section->radiusM * cos($heading + $side * M_PI / 2);
                $centreY = $y + $section->radiusM * sin($heading + $side * M_PI / 2);

                for ($step = 1; $step <= $steps; $step++) {
                    $turned = $angle * $step / $steps;
                    $points[] = [
                        'x' => $centreX + $section->radiusM * cos($heading - $side * M_PI / 2 + $side * $turned),
                        'y' => $centreY + $section->radiusM * sin($heading - $side * M_PI / 2 + $side * $turned),
                        'distance_m' => $distance + $section->lengthM * $step / $steps,
                        'section' => $index,
                    ];
                }

                $x = $centreX + $section->radiusM * cos($heading - $side * M_PI / 2 + $side * $angle);
                $y = $centreY + $section->radiusM * sin($heading - $side * M_PI / 2 + $side * $angle);
                $heading += $side * $angle;
                $turn += $side * $section->angleDeg;
            }

            $distance += $section->lengthM;
        }

        $gap = $thread->isCircuit() ? hypot($x, $y) : 0.0;

        if ($close && $gap > 0.0) {
            foreach ($points as &$point) {
                $share = $point['distance_m'] / $distance;
                $point['x'] -= $x * $share;
                $point['y'] -= $y * $share;
            }
        }

        return ['points' => $points, 'gap_m' => $gap, 'turn_deg' => $turn];
    }
}
