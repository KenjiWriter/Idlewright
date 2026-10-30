<?php

declare(strict_types=1);

namespace App\Domain\Run;

use InvalidArgumentException;

/**
 * The constants of the model of a lap: how the statistics of a car turn into acceleration, speed in a corner and braking. They are the rules of the game
 * (the section `dynamics`), not of a track, and every one of them is a number a studio can move.
 *
 * - `power`: the share of the power over mass of the car that reaches the road as acceleration (a point of `accel` is this many W per kg).
 * - `lateralG`, `launchG`, `brakeG`: what a statistic of 100 gives, in g: sideways in a corner, forward off the line, and backwards on the brakes.
 * - `agility`: how much of a corner is down to handling and how much to grip: `agilityMax` in a hairpin, falling to `agilityMin` at `agilityRadiusM`.
 * - `topSpeedOnSurface`: what is left of the top speed on a surface (gravel, mud and snow hold a car back, asphalt does not).
 * - `roughRefKmh`, `roughExponent`: how fast a car with enough clearance goes over a rough stretch, and how fast one with too little slows down.
 */
final readonly class Dynamics
{
    /**
     * @param  array<string, float>  $topSpeedOnSurface  by the surfaces of `Surface`
     */
    public function __construct(
        public float $power = 0.60,
        public float $lateralG = 0.95,
        public float $brakeG = 1.00,
        public float $launchG = 0.95,
        public float $agilityMin = 0.25,
        public float $agilityMax = 0.75,
        public float $agilityRadiusM = 190.0,
        public array $topSpeedOnSurface = [
            Surface::ASPHALT_DRY => 1.0,
            Surface::ASPHALT_WET => 1.0,
            Surface::GRAVEL_DRY => 0.85,
            Surface::MUD => 0.60,
            Surface::SNOW => 0.70,
        ],
        public float $roughRefKmh = 90.0,
        public float $roughExponent = 0.5,
    ) {
        foreach (['power' => $power, 'lateral g' => $lateralG, 'braking g' => $brakeG, 'launch g' => $launchG, 'agility radius' => $agilityRadiusM, 'rough speed' => $roughRefKmh, 'rough exponent' => $roughExponent] as $name => $value) {
            if ($value <= 0) {
                throw new InvalidArgumentException("The {$name} of the dynamics must be positive.");
            }
        }

        if ($agilityMin < 0 || $agilityMax > 1 || $agilityMin > $agilityMax) {
            throw new InvalidArgumentException('The share of handling in a corner is between 0 and 1, and the least of it is not more than the most.');
        }

        foreach (Surface::ALL as $surface) {
            if (! isset($topSpeedOnSurface[$surface]) || $topSpeedOnSurface[$surface] <= 0 || $topSpeedOnSurface[$surface] > 1) {
                throw new InvalidArgumentException("The top speed on {$surface} is a share of it, more than nothing and at most all of it.");
            }
        }
    }

    /** The share of handling in a corner of this radius: the most in a hairpin, the least in a wide bend. */
    public function agility(float $radiusM): float
    {
        return min($this->agilityMax, max($this->agilityMin, $this->agilityMax - ($this->agilityMax - $this->agilityMin) * $radiusM / $this->agilityRadiusM));
    }
}
