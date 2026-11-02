<?php

declare(strict_types=1);

use App\Domain\Competition\PerformanceClasses;
use App\Domain\Competition\PerformanceIndex;

const PI_WEIGHTS = ['accel' => 217_000, 'top_speed' => 127_000, 'grip' => 230_000, 'handling' => 197_000, 'braking' => 163_000, 'clearance' => 66_000];

/** @return array<string, int> the statistics of a car in thousandths, scaled by a ratio in percent from the reference car */
function piStats(int $percent): array
{
    $reference = ['accel' => 97_000, 'top_speed' => 175_000, 'grip' => 90_000, 'handling' => 88_000, 'braking' => 90_000, 'clearance' => 20_000];

    return array_map(static fn (int $value): int => intdiv($value * $percent, 100), $reference);
}

it('gives the reference car exactly the reference PI', function () {
    expect(PerformanceIndex::of(piStats(100), piStats(100), PI_WEIGHTS, 4_000_000, 300))->toBe(300);
});

it('grows with the statistics of the car, in proportion', function () {
    expect(PerformanceIndex::of(piStats(50), piStats(100), PI_WEIGHTS, 4_000_000, 300))->toBe(150)
        ->and(PerformanceIndex::of(piStats(150), piStats(100), PI_WEIGHTS, 4_000_000, 300))->toBe(450)
        ->and(PerformanceIndex::of(piStats(200), piStats(100), PI_WEIGHTS, 4_000_000, 300))->toBe(600);
});

it('counts the weights, not the number of statistics: a stat that weighs more moves the PI more', function () {
    $faster = piStats(100);
    $faster['grip'] *= 2;
    $roomier = piStats(100);
    $roomier['clearance'] *= 2;

    expect(PerformanceIndex::of($faster, piStats(100), PI_WEIGHTS, 4_000_000, 300))->toBe(369)
        ->and(PerformanceIndex::of($roomier, piStats(100), PI_WEIGHTS, 4_000_000, 300))->toBe(320);
});

it('caps the ratio of a statistic, so one huge number cannot buy a class', function () {
    $stats = piStats(100);
    $stats['accel'] *= 100;

    // accel weighs 21.7% and counts at most four times the reference: 300 * (1 + 0.217 * 3)
    expect(PerformanceIndex::of($stats, piStats(100), PI_WEIGHTS, 4_000_000, 300))->toBe(495);
});

it('does not need the weights to add up to a million', function () {
    $half = array_map(static fn (int $weight): int => intdiv($weight, 2), PI_WEIGHTS);

    expect(PerformanceIndex::of(piStats(150), piStats(100), $half, 4_000_000, 300))->toBe(450);
});

it('refuses numbers that cannot make an index', function () {
    expect(fn () => PerformanceIndex::of(piStats(100), [...piStats(100), 'accel' => 0], PI_WEIGHTS, 4_000_000, 300))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PerformanceIndex::of(piStats(100), piStats(100), [], 4_000_000, 300))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PerformanceIndex::of(piStats(100), piStats(100), PI_WEIGHTS, 0, 300))->toThrow(InvalidArgumentException::class);
});

describe('the classes', function () {
    function piClasses(): PerformanceClasses
    {
        return new PerformanceClasses(['D' => 249, 'C' => 399, 'B' => 549, 'A' => 699, 'S' => 849], 'X');
    }

    it('puts a car in the class whose limit it is under, and the strongest above every limit in the top class', function (int $pi, string $class) {
        expect(piClasses()->of($pi))->toBe($class);
    })->with([[0, 'D'], [249, 'D'], [250, 'C'], [372, 'C'], [399, 'C'], [400, 'B'], [699, 'A'], [700, 'S'], [849, 'S'], [850, 'X'], [5_000, 'X']]);

    it('says what a class holds', function () {
        expect(piClasses()->bounds('D'))->toBe(['min' => 0, 'max' => 249])
            ->and(piClasses()->bounds('C'))->toBe(['min' => 250, 'max' => 399])
            ->and(piClasses()->bounds('X'))->toBe(['min' => 850, 'max' => null])
            ->and(piClasses()->names())->toBe(['D', 'C', 'B', 'A', 'S', 'X'])
            ->and(fn () => piClasses()->bounds('Z'))->toThrow(InvalidArgumentException::class);
    });

    it('needs limits that grow and a top class of its own', function () {
        expect(fn () => new PerformanceClasses(['D' => 300, 'C' => 300], 'B'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new PerformanceClasses(['D' => 100], 'D'))->toThrow(InvalidArgumentException::class);
    });
});
