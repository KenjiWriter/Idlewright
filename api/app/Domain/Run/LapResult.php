<?php

declare(strict_types=1);

namespace App\Domain\Run;

/**
 * A lap on a thread, as `LapSimulator` drove it: the time and, section by section, how the car got through. The time is in seconds as a float; the
 * freezer rounds it to whole milliseconds and stores that.
 */
final readonly class LapResult
{
    /**
     * @param  list<SectionRun>  $sections
     */
    public function __construct(public array $sections, public float $returnSeconds = 0.0) {}

    /** The lap, in seconds: the sections and the way back. */
    public function seconds(): float
    {
        return array_sum(array_map(static fn (SectionRun $run): float => $run->seconds(), $this->sections)) + $this->returnSeconds;
    }

    /** The lap in whole milliseconds, at least one. */
    public function lapMs(): int
    {
        return max(1, (int) round($this->seconds() * 1000));
    }

    /** How far the car goes in a lap, in metres. */
    public function lengthM(): float
    {
        return array_sum(array_map(static fn (SectionRun $run): float => $run->section->lengthM, $this->sections));
    }

    /** The speed over the whole lap, in km/h, the way back not counted. */
    public function averageKmh(): float
    {
        $seconds = $this->seconds() - $this->returnSeconds;

        return $seconds > 0 ? $this->lengthM() / $seconds * 3.6 : 0.0;
    }

    /** The highest speed of the lap, in km/h. */
    public function peakKmh(): float
    {
        return max(array_map(static fn (SectionRun $run): float => $run->peak, $this->sections)) * 3.6;
    }

    /**
     * Where the time of the lap went, in seconds: accelerating and holding the limit on the straights, braking for the corners, and the corners themselves.
     *
     * @return array{accelerating: float, holding: float, braking: float, cornering: float, return: float}
     */
    public function split(): array
    {
        $split = ['accelerating' => 0.0, 'holding' => 0.0, 'braking' => 0.0, 'cornering' => 0.0, 'return' => $this->returnSeconds];

        foreach ($this->sections as $run) {
            if ($run->section->isCorner()) {
                $split['cornering'] += $run->seconds();

                continue;
            }

            $split['accelerating'] += $run->accelerateSeconds;
            $split['holding'] += $run->holdSeconds;
            $split['braking'] += $run->brakeSeconds;
        }

        return $split;
    }

    /**
     * The speed along the lap, for a picture of it: a point every `$stepM` metres (and the last one), with the time the car is there, from the start of the lap.
     * The time is made from the speeds, so it is close to the time of the lap but not the same to the last digit.
     *
     * @return list<array{distance_m: float, speed_kmh: float, elapsed_s: float}>
     */
    public function samples(float $stepM = 10.0): array
    {
        $points = [];
        $distance = 0.0;
        $elapsed = 0.0;
        $lastDistance = null;
        $lastSpeed = null;

        foreach ($this->sections as $run) {
            $length = $run->section->lengthM;
            $count = max(1, (int) ceil($length / $stepM));

            for ($index = 0; $index <= $count; $index++) {
                $at = $length * $index / $count;
                $speed = max(0.1, $run->speedAt($at));

                if ($lastDistance !== null && $distance + $at > $lastDistance) {
                    $elapsed += ($distance + $at - $lastDistance) * (1 / $lastSpeed + 1 / $speed) / 2;
                }

                $lastDistance = $distance + $at;
                $lastSpeed = $speed;

                if ($index < $count || $run === $this->sections[array_key_last($this->sections)]) {
                    $points[] = ['distance_m' => $distance + $at, 'speed_kmh' => $speed * 3.6, 'elapsed_s' => $elapsed];
                }
            }

            $distance += $length;
        }

        return $points;
    }
}
