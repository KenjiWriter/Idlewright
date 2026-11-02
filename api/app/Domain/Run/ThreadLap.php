<?php

declare(strict_types=1);

namespace App\Domain\Run;

use InvalidArgumentException;

/**
 * What the lap of a car is driven from when the track says it is driven on its thread: the thread, the constants of the model, the car on every surface of the
 * thread in every weather of the region, and the reference car of the track (the one the base lap of the track is for) on the same thread in dry weather.
 *
 * The lap of the car is the base lap of the track times how long the car takes on the thread over how long the reference car does. So the reference car drives
 * exactly the base lap of the track, whatever the constants of the model and the thread come to, and every other car is faster or slower than it by what the
 * model says: the pay and the fuel of the track, which hang on the base lap, do not move when the model does.
 */
final readonly class ThreadLap
{
    /** How long the reference car takes on the thread, in dry weather, in seconds. */
    public float $referenceSeconds;

    private LapSimulator $simulator;

    /**
     * @param  array<string, array<string, CarOnSurface>>  $carsByWeather  the car for every surface of the thread (`Thread::surfaces`), by weather; it has the dry weather
     * @param  array<string, CarOnSurface>  $reference  the reference car for every surface of the thread
     */
    public function __construct(public Thread $thread, public Dynamics $dynamics, public array $carsByWeather, public array $reference)
    {
        $this->simulator = new LapSimulator($dynamics);
        $this->referenceSeconds = $this->simulator->simulate($thread, $reference)->seconds();

        if (! isset($carsByWeather['dry'])) {
            throw new InvalidArgumentException('The lap on a thread needs the car in dry weather.');
        }
    }

    /** The lap of the car in a weather, in whole milliseconds, for a track whose base lap is `$baseLapMs`. */
    public function lapMs(string $weather, int $baseLapMs): int
    {
        $cars = $this->carsByWeather[$weather] ?? $this->carsByWeather['dry'];

        return max(1, (int) round($baseLapMs * $this->simulator->simulate($this->thread, $cars)->seconds() / $this->referenceSeconds));
    }
}
