<?php

declare(strict_types=1);

namespace App\Domain\Run;

use InvalidArgumentException;

/**
 * The thread of a track: its line as an ordered chain of sections, and how a lap is driven on it.
 *
 * - A `circuit` is a loop driven flying: the car crosses the line at the speed it left it, so the lap is the steady state of the loop.
 * - A `run` is driven from a standstill every time (a drag strip, a sprint, a hill climb), and `returnSeconds` is what the way back to the start
 *   adds to every lap, whatever the car.
 */
final readonly class Thread
{
    public const CIRCUIT = 'circuit';

    public const RUN = 'run';

    /**
     * @param  list<Section>  $sections  in the order they are driven
     * @param  float  $returnSeconds  the way back to the start of a `run`; a circuit has none
     * @param  string  $surface  what the sections that name none are made of: the surface of the track
     */
    public function __construct(
        public string $mode,
        public array $sections,
        public float $returnSeconds = 0.0,
        public string $surface = 'asphalt',
    ) {
        if (! in_array($mode, [self::CIRCUIT, self::RUN], true)) {
            throw new InvalidArgumentException("A thread is a circuit or a run, not {$mode}.");
        }

        if ($sections === [] || ! array_is_list($sections)) {
            throw new InvalidArgumentException('A thread needs at least one section, in a list.');
        }

        foreach ($sections as $section) {
            if (! $section instanceof Section) {
                throw new InvalidArgumentException('A thread is made of sections.');
            }
        }

        if (! in_array($surface, Section::SURFACES, true)) {
            throw new InvalidArgumentException("There is no surface {$surface}; a track is asphalt or gravel.");
        }

        if ($returnSeconds < 0) {
            throw new InvalidArgumentException('The way back cannot take less than nothing.');
        }
    }

    /** How long a lap is, in metres: what the sections add up to. */
    public function lengthM(): float
    {
        return array_sum(array_map(static fn (Section $section): float => $section->lengthM, $this->sections));
    }

    /** What a section is made of: its own surface, or the one of the track. */
    public function surfaceOf(Section $section): string
    {
        return $section->surface ?? $this->surface;
    }

    /**
     * The surfaces the sections are made of, so that whoever makes the car for the thread knows for which ones it has to.
     *
     * @return list<string>
     */
    public function surfaces(): array
    {
        $surfaces = [];

        foreach ($this->sections as $section) {
            $surfaces[$this->surfaceOf($section)] = true;
        }

        $names = array_keys($surfaces);
        sort($names);

        return $names;
    }

    public function isCircuit(): bool
    {
        return $this->mode === self::CIRCUIT;
    }
}
