<?php

declare(strict_types=1);

use App\Domain\Competition\LuckTable;
use App\Domain\Competition\MatchEngine;

function engineLuck(): LuckTable
{
    static $table = null;

    return $table ??= LuckTable::fromFile(dirname(__DIR__, 4).'/content/tables/luck-ppm.json');
}

/** @return array<string, array<string, int>> */
function engineLaps(int $street, int $gravel): array
{
    return ['street' => ['dry' => $street, 'rain' => intdiv($street * 11, 10)], 'gravel' => ['dry' => $gravel, 'rain' => intdiv($gravel * 12, 10)]];
}

const ENGINE_RUNS = [['track' => 'street', 'weather' => 'dry'], ['track' => 'gravel', 'weather' => 'dry'], ['track' => 'street', 'weather' => 'rain']];

it('plays the same match again with the same result, to the millisecond', function () {
    $first = MatchEngine::play(engineLaps(75_000, 95_000), engineLaps(76_000, 94_000), ENGINE_RUNS, 'seed-1', 't1:3:0', engineLuck(), 20_000);
    $again = MatchEngine::play(engineLaps(75_000, 95_000), engineLaps(76_000, 94_000), ENGINE_RUNS, 'seed-1', 't1:3:0', engineLuck(), 20_000);

    expect($again->toArray())->toBe($first->toArray())
        ->and($first->runs)->toHaveCount(3)
        ->and($first->aWins + $first->bWins)->toBe(3)
        ->and($first->winner)->toBe($first->aWins > $first->bWins ? 'a' : 'b');
});

it('draws other luck for another match or another seed', function () {
    $laps = engineLaps(75_000, 95_000);
    $base = MatchEngine::play($laps, $laps, ENGINE_RUNS, 'seed-1', 'm1', engineLuck(), 20_000)->toArray();

    expect(MatchEngine::play($laps, $laps, ENGINE_RUNS, 'seed-1', 'm2', engineLuck(), 20_000)->toArray())->not->toBe($base)
        ->and(MatchEngine::play($laps, $laps, ENGINE_RUNS, 'seed-2', 'm1', engineLuck(), 20_000)->toArray())->not->toBe($base);
});

it('adds the luck of the run to the lap, from the table, with no luck at all when the width is nothing', function () {
    $result = MatchEngine::play(engineLaps(75_000, 95_000), engineLaps(76_000, 94_000), ENGINE_RUNS, 's', 'm', engineLuck(), 0);

    expect(array_column($result->runs, 'a_run_ms'))->toBe(array_column($result->runs, 'a_ms'))
        ->and(array_column($result->runs, 'b_run_ms'))->toBe(array_column($result->runs, 'b_ms'))
        // Street: A is quicker; gravel: B is quicker; rain on the street: A is quicker. A takes the match.
        ->and(array_column($result->runs, 'winner'))->toBe(['a', 'b', 'a'])
        ->and($result->winner)->toBe('a')
        ->and($result->marginMs)->toBe((76_000 - 75_000) + (94_000 - 95_000) + (intdiv(76_000 * 11, 10) - intdiv(75_000 * 11, 10)));
});

it('keeps the luck inside the table: no run is more than 3.3 sigma off its lap', function () {
    $laps = engineLaps(75_000, 95_000);

    for ($i = 0; $i < 300; $i++) {
        foreach (MatchEngine::play($laps, $laps, ENGINE_RUNS, 'seed', "m{$i}", engineLuck(), 20_000)->runs as $run) {
            expect(abs($run['a_run_ms'] - $run['a_ms']) * 10_000 <= $run['a_ms'] * 662)->toBeTrue();
        }
    }
});

it('gives the quicker car the match as often as the numbers say: equal cars half and half, 2% quicker about 85% (sigma 2%, best of three)', function () {
    $wins = ['equal' => 0, 'quicker' => 0];
    $matches = 2_000;
    $same = engineLaps(75_000, 95_000);
    $quicker = engineLaps(73_500, 93_100);

    for ($i = 0; $i < $matches; $i++) {
        $wins['equal'] += MatchEngine::play($same, $same, ENGINE_RUNS, 'seed', "e{$i}", engineLuck(), 20_000)->winnerIsA() ? 1 : 0;
        $wins['quicker'] += MatchEngine::play($quicker, $same, ENGINE_RUNS, 'seed', "q{$i}", engineLuck(), 20_000)->winnerIsA() ? 1 : 0;
    }

    expect($wins['equal'] / $matches)->toBeBetween(0.46, 0.54)
        ->and($wins['quicker'] / $matches)->toBeBetween(0.81, 0.89);
});

it('decides a run that ends in a tie from the seed, not always for the same side', function () {
    $laps = engineLaps(75_000, 95_000);
    $sides = [];

    for ($i = 0; $i < 60; $i++) {
        $sides[MatchEngine::play($laps, $laps, [ENGINE_RUNS[0]], 'seed', "t{$i}", engineLuck(), 0)->winner] = true;
    }

    expect(array_keys($sides))->toContain('a', 'b');
});

it('wants an odd number of runs and a lap for every run', function () {
    $laps = engineLaps(75_000, 95_000);

    expect(fn () => MatchEngine::play($laps, $laps, [], 's', 'm', engineLuck(), 20_000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MatchEngine::play($laps, $laps, array_slice(ENGINE_RUNS, 0, 2), 's', 'm', engineLuck(), 20_000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MatchEngine::play($laps, ['street' => ['dry' => 75_000]], ENGINE_RUNS, 's', 'm', engineLuck(), 20_000))->toThrow(InvalidArgumentException::class);
});
