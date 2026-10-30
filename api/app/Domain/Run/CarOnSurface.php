<?php

declare(strict_types=1);

namespace App\Domain\Run;

use InvalidArgumentException;

/**
 * What a car is on one surface in one weather, as the lap is driven by it: the statistics of the car with the tires on this surface, the drive and the
 * weather already multiplied in (`RunInputsFactory` makes them, as it makes the multipliers of a weather today). The simulator counts nothing a second time.
 */
final readonly class CarOnSurface
{
    /**
     * @param  string  $surface  one of the five of `Surface`: what the weather has made of the surface of the section
     * @param  float  $accel  power over mass, in kW per tonne
     * @param  float  $topSpeedKmh  the top speed on a surface that does not slow the car
     */
    public function __construct(
        public string $surface,
        public float $accel,
        public float $topSpeedKmh,
        public float $grip,
        public float $handling,
        public float $braking,
        public float $clearance,
    ) {
        if (! in_array($surface, Surface::ALL, true)) {
            throw new InvalidArgumentException("There is no surface {$surface}.");
        }

        foreach (['accel' => $accel, 'top speed' => $topSpeedKmh, 'grip' => $grip, 'handling' => $handling, 'braking' => $braking, 'clearance' => $clearance] as $name => $value) {
            if ($value < 0) {
                throw new InvalidArgumentException("The {$name} of a car cannot be negative.");
            }
        }
    }
}
