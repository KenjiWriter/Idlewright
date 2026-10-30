<?php

declare(strict_types=1);

namespace App\Domain\Run;

/**
 * How a car moves on one surface: the closed forms of the model of a lap.
 *
 * - Off the line the car is held by its grip: a constant acceleration `launch` up to the speed `launchEnd` at which the engine can no longer give more.
 * - Above it the power decides: a = rho / v - c * v^2, where rho is the power over mass that reaches the road and c is chosen so that the acceleration is
 *   nothing at the top speed. In u = v^3 this is a straight line, du/ds = 3 * rho - 3 * c * u, so the speed after a distance and the distance to a speed
 *   are closed forms (one exponent, one logarithm), and no step of time is ever taken.
 * - Braking is a constant deceleration, and a corner is driven at the speed the grip and the handling allow for its radius.
 *
 * A statistic of nothing would divide by nothing, so every statistic counts for at least the floors below: a car cannot be worse than that on the model.
 */
final readonly class Motion
{
    private const MIN_STAT = 1.0;

    private const MIN_TOP_SPEED_MS = 5.0;

    private const GRAVITY = 9.81;

    /** Gauss-Legendre, eight points, on [-1, 1]: nodes and weights. */
    private const NODES = [-0.9602898565, -0.7966664774, -0.5255324099, -0.1834346425, 0.1834346425, 0.5255324099, 0.7966664774, 0.9602898565];

    private const WEIGHTS = [0.1012285363, 0.2223810345, 0.3137066459, 0.3626837834, 0.3626837834, 0.3137066459, 0.2223810345, 0.1012285363];

    /** The top speed in m/s on this surface. */
    public float $topSpeed;

    /** Power over mass that reaches the road, in m^2/s^3. */
    public float $rho;

    /** The acceleration the grip allows off the line, in m/s^2. */
    public float $launch;

    /** The deceleration of the brakes, in m/s^2. */
    public float $brake;

    /** The speed at which the power takes over from the grip, in m/s. */
    public float $launchEnd;

    public float $clearance;

    private float $grip;

    private float $handling;

    public function __construct(CarOnSurface $car, private Dynamics $dynamics)
    {
        $this->topSpeed = max($car->topSpeedKmh / 3.6, self::MIN_TOP_SPEED_MS) * $dynamics->topSpeedOnSurface[$car->surface];
        $this->rho = max($car->accel, self::MIN_STAT) * $dynamics->power;
        $this->grip = max($car->grip, self::MIN_STAT);
        $this->handling = max($car->handling, self::MIN_STAT);
        $this->launch = $dynamics->launchG * self::GRAVITY * $this->grip / 100;
        $this->brake = $dynamics->brakeG * self::GRAVITY * max($car->braking, self::MIN_STAT) / 100;
        $this->clearance = $car->clearance;

        // Where the power curve meets the grip: c v^3 + launch v - rho = 0, which has one positive root (Cardano).
        $c = $this->rho / $this->topSpeed ** 3;
        $p = $this->launch / $c;
        $q = -$this->rho / $c;
        $discriminant = sqrt($q * $q / 4 + $p ** 3 / 27);
        $this->launchEnd = self::cubeRoot(-$q / 2 + $discriminant) + self::cubeRoot(-$q / 2 - $discriminant);
    }

    /** The speed at which this car can drive a corner of this radius, in m/s: sqrt(lateral acceleration * radius), the grip and the handling shared by the radius. */
    public function cornerSpeed(float $radiusM): float
    {
        $share = $this->dynamics->agility($radiusM);
        $lateral = $this->dynamics->lateralG * self::GRAVITY * ($this->grip ** (1 - $share)) * ($this->handling ** $share) / 100;

        return sqrt($lateral * $radiusM);
    }

    /** The speed after accelerating over a distance, from a speed, in m/s. */
    public function speedAfter(float $from, float $distance): float
    {
        if ($from >= $this->launchEnd) {
            return $this->powerSpeed($from, $distance);
        }

        $held = ($this->launchEnd ** 2 - $from ** 2) / (2 * $this->launch);

        return $distance <= $held ? sqrt($from * $from + 2 * $this->launch * $distance) : $this->powerSpeed($this->launchEnd, $distance - $held);
    }

    /** The distance over which the car accelerates from one speed to a higher one (below the top speed), in m. */
    public function distanceTo(float $from, float $to): float
    {
        if ($to <= $from) {
            return 0.0;
        }

        $distance = 0.0;

        if ($from < $this->launchEnd) {
            $held = min($to, $this->launchEnd);
            $distance += ($held ** 2 - $from ** 2) / (2 * $this->launch);
            $from = $held;

            if ($to <= $this->launchEnd) {
                return $distance;
            }
        }

        $top = $this->topSpeed ** 3;

        return $distance + $top / (3 * $this->rho) * log(($top - $from ** 3) / ($top - $to ** 3));
    }

    /** The time it takes to accelerate over a distance, from a speed, in s: closed form while the grip holds the car, quadrature in the distance after it. */
    public function timeFor(float $from, float $distance): float
    {
        if ($distance <= 0) {
            return 0.0;
        }

        $time = 0.0;

        if ($from < $this->launchEnd) {
            $held = ($this->launchEnd ** 2 - $from ** 2) / (2 * $this->launch);

            if ($distance <= $held) {
                return (sqrt($from * $from + 2 * $this->launch * $distance) - $from) / $this->launch;
            }

            $time += ($this->launchEnd - $from) / $this->launch;
            $distance -= $held;
            $from = $this->launchEnd;
        }

        $half = $distance / 2;

        foreach (self::NODES as $index => $node) {
            $time += self::WEIGHTS[$index] * $half / $this->powerSpeed($from, $half * (1 + $node));
        }

        return $time;
    }

    /** The speed on the way down to a speed at the end of a distance of braking: the one to be at, so that the brakes bring the car to $to over it. */
    public function brakingSpeed(float $to, float $distance): float
    {
        return sqrt($to * $to + 2 * $this->brake * max(0.0, $distance));
    }

    private function powerSpeed(float $from, float $distance): float
    {
        $top = $this->topSpeed ** 3;

        return ($top - ($top - $from ** 3) * exp(-3 * $this->rho * $distance / $top)) ** (1 / 3);
    }

    private static function cubeRoot(float $x): float
    {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }
}
