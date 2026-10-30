<?php

declare(strict_types=1);

namespace App\Domain\Run;

use InvalidArgumentException;

/**
 * Drives a lap on a thread: a point mass over the sections, in the order of a quasi-steady-state pass.
 *
 * 1. Every section has a speed it cannot be driven faster than (`cap`): the speed of a corner for its radius, the top speed on the surface, a speed limit,
 *    and, on a rough stretch, what the clearance of the car lets it do.
 * 2. The speed at each junction is the lower of the caps of the sections on either side of it. A backward pass lowers it where the brakes could not bring
 *    the car down in the section before (`sqrt(v_next^2 + 2 * brake * length)`), a forward pass lowers it where the engine could not get the car up to it
 *    (`Motion::speedAfter`). A circuit does the two passes three times round, which is the steady state of a loop; a run starts from a standstill.
 * 3. Inside a section the car accelerates, holds its cap and brakes, so that it leaves at the speed of the junction after it. When the cap is not reached
 *    the point where the acceleration meets the braking is found by 40 steps of bisection, the same 40 on every machine.
 *
 * Floating point numbers are used here, as in the freezer, which is the one place that rounds them to whole milliseconds and stores the result. Nothing
 * here reads a clock, a random number or a database, and nothing is ever computed per lap in a settlement: a lap is solved once for a car, a thread and
 * a weather, when the run is frozen.
 */
final class LapSimulator
{
    private const BISECTION_STEPS = 40;

    private const LOOPS = 3;

    /** The top speed itself is never reached, only approached; a section holds at this share of it. */
    private const TOP_SPEED_HOLD = 0.999;

    public function __construct(private readonly Dynamics $dynamics = new Dynamics) {}

    /**
     * @param  array<string, CarOnSurface>  $cars  the car for every surface the sections of the thread are made of (`Thread::surfaces`), by that surface:
     *                                             what the weather has made of it is in the car (`CarOnSurface::$surface`)
     */
    public function simulate(Thread $thread, array $cars): LapResult
    {
        $sections = $thread->sections;
        $count = count($sections);
        $motions = [];
        $caps = [];

        foreach ($sections as $index => $section) {
            $surface = $thread->surfaceOf($section);
            $car = $cars[$surface] ?? throw new InvalidArgumentException("The thread has a section on {$surface}, and the car was not given for it.");
            $motion = new Motion($car, $this->dynamics);
            $cap = $section->isCorner() ? $motion->cornerSpeed($section->radiusM) : $motion->topSpeed;

            if ($section->limitKmh !== null) {
                $cap = min($cap, $section->limitKmh / 3.6);
            }

            if ($section->rough > 0) {
                $cap = min($cap, $this->dynamics->roughRefKmh / 3.6 * min(1.0, $motion->clearance / $section->rough) ** $this->dynamics->roughExponent);
            }

            $motions[$index] = $motion;
            $caps[$index] = min($cap, $motion->topSpeed * self::TOP_SPEED_HOLD);
        }

        $junctions = $this->junctions($thread, $motions, $caps);
        $runs = [];

        foreach ($sections as $index => $section) {
            $runs[] = $this->drive($section, $motions[$index], $junctions[$index], $junctions[$index + 1], $caps[$index]);
        }

        return new LapResult($runs, $thread->isCircuit() ? 0.0 : $thread->returnSeconds);
    }

    /**
     * The speed at the start of every section and at the end of the last one (n + 1 numbers), in m/s.
     *
     * @param  array<int, Motion>  $motions
     * @param  array<int, float>  $caps
     * @return list<float>
     */
    private function junctions(Thread $thread, array $motions, array $caps): array
    {
        $count = count($caps);
        $speeds = array_fill(0, $count + 1, INF);

        for ($index = 0; $index < $count; $index++) {
            $speeds[$index] = min($speeds[$index], $caps[$index]);
            $speeds[$index + 1] = min($speeds[$index + 1], $caps[$index]);
        }

        $circuit = $thread->isCircuit();

        if ($circuit) {
            $speeds[0] = $speeds[$count] = min($speeds[0], $speeds[$count]);
        } else {
            $speeds[0] = 0.0;
        }

        for ($loop = 0; $loop < ($circuit ? self::LOOPS : 1); $loop++) {
            for ($index = $count - 1; $index >= 0; $index--) {
                $speeds[$index] = min($speeds[$index], $motions[$index]->brakingSpeed($speeds[$index + 1], $thread->sections[$index]->lengthM));
            }

            if ($circuit) {
                $speeds[0] = $speeds[$count] = min($speeds[0], $speeds[$count]);
            }

            for ($index = 0; $index < $count; $index++) {
                $speeds[$index + 1] = min($speeds[$index + 1], $motions[$index]->speedAfter($speeds[$index], $thread->sections[$index]->lengthM));
            }

            if ($circuit) {
                $speeds[0] = $speeds[$count] = min($speeds[0], $speeds[$count]);
            }
        }

        return $speeds;
    }

    /** One section, entered at `$entry` and left at `$exit`, never above `$cap`. */
    private function drive(Section $section, Motion $motion, float $entry, float $exit, float $cap): SectionRun
    {
        $length = $section->lengthM;
        $climb = $entry < $cap ? $motion->distanceTo($entry, $cap) : 0.0;
        $brake = ($cap ** 2 - $exit ** 2) / (2 * $motion->brake);

        // The cap is reached: accelerate to it, hold it, brake from it.
        if ($climb + $brake <= $length) {
            return new SectionRun(
                $section,
                $motion,
                $entry,
                $cap,
                $exit,
                $cap,
                $climb > 0 ? $motion->timeFor($entry, $climb) : 0.0,
                ($length - $climb - $brake) / $cap,
                ($cap - $exit) / $motion->brake,
                $climb,
                $length - $climb - $brake,
            );
        }

        // Not even the exit speed is reached by accelerating the whole section: no braking at all.
        $reached = $motion->speedAfter($entry, $length);

        if ($reached <= $exit) {
            return new SectionRun($section, $motion, $entry, $reached, $exit, $cap, $motion->timeFor($entry, $length), 0.0, 0.0, $length, 0.0);
        }

        // The peak lies below the cap: where the acceleration from the entry meets the braking to the exit.
        $low = 0.0;
        $high = $length;

        for ($step = 0; $step < self::BISECTION_STEPS; $step++) {
            $middle = ($low + $high) / 2;

            if ($motion->speedAfter($entry, $middle) > $motion->brakingSpeed($exit, $length - $middle)) {
                $high = $middle;
            } else {
                $low = $middle;
            }
        }

        $at = ($low + $high) / 2;
        $peak = $motion->speedAfter($entry, $at);

        return new SectionRun($section, $motion, $entry, $peak, $exit, $cap, $motion->timeFor($entry, $at), 0.0, ($peak - $exit) / $motion->brake, $at, 0.0);
    }
}
