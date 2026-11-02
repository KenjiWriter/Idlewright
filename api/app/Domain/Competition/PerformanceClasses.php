<?php

declare(strict_types=1);

namespace App\Domain\Competition;

use InvalidArgumentException;

/**
 * The classes of the cars by their performance index: each class holds the cars up to its limit, and the last class has no limit. A tournament of a class takes
 * the cars of that class only, so a good tune is an advantage in its class and a better car opens the next class.
 */
final readonly class PerformanceClasses
{
    /** @var list<string> the classes from the weakest to the strongest; the last one has no limit */
    private array $order;

    /**
     * @param  array<string, int>  $maxPi  the highest PI of each class but the last, in the order of the classes (the limits grow)
     * @param  string  $top  the class above the highest limit
     */
    public function __construct(private array $maxPi, private string $top)
    {
        $previous = 0;

        foreach ($maxPi as $class => $limit) {
            if ($limit <= $previous) {
                throw new InvalidArgumentException("The limit of the class {$class} must be above the one before it.");
            }

            $previous = $limit;
        }

        if ($top === '' || array_key_exists($top, $maxPi)) {
            throw new InvalidArgumentException('The top class needs a name that no other class has.');
        }

        $this->order = [...array_keys($maxPi), $top];
    }

    /** The class of a car with this PI. */
    public function of(int $pi): string
    {
        foreach ($this->maxPi as $class => $limit) {
            if ($pi <= $limit) {
                return $class;
            }
        }

        return $this->top;
    }

    /**
     * The PI a car needs to be in the class and the PI above which it is not any more (null for the top class).
     *
     * @return array{min: int, max: int|null}
     */
    public function bounds(string $class): array
    {
        $min = 0;

        foreach ($this->maxPi as $name => $limit) {
            if ($name === $class) {
                return ['min' => $min, 'max' => $limit];
            }

            $min = $limit + 1;
        }

        if ($class === $this->top) {
            return ['min' => $min, 'max' => null];
        }

        throw new InvalidArgumentException("Unknown class {$class}.");
    }

    /** @return list<string> the classes from the weakest to the strongest */
    public function names(): array
    {
        return $this->order;
    }
}
