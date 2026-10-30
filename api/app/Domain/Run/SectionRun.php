<?php

declare(strict_types=1);

namespace App\Domain\Run;

/**
 * How one section of the thread was driven: the speeds it was entered, peaked and left at, and the time and the distance spent accelerating, holding
 * the limit (the top speed, a speed limit, the speed of a corner) and braking. Speeds are in m/s.
 */
final readonly class SectionRun
{
    public function __construct(
        public Section $section,
        public Motion $motion,
        public float $entry,
        public float $peak,
        public float $exit,
        public float $cap,
        public float $accelerateSeconds,
        public float $holdSeconds,
        public float $brakeSeconds,
        public float $accelerateM,
        public float $holdM,
    ) {}

    public function seconds(): float
    {
        return $this->accelerateSeconds + $this->holdSeconds + $this->brakeSeconds;
    }

    /** The speed at a distance from the start of the section, in m/s: accelerating, holding, then braking down to the exit speed. */
    public function speedAt(float $distanceM): float
    {
        $distanceM = max(0.0, min($this->section->lengthM, $distanceM));

        if ($distanceM <= $this->accelerateM) {
            return $this->motion->speedAfter($this->entry, $distanceM);
        }

        if ($distanceM <= $this->accelerateM + $this->holdM) {
            return $this->cap;
        }

        return $this->motion->brakingSpeed($this->exit, $this->section->lengthM - $distanceM);
    }
}
