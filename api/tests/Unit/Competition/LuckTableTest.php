<?php

declare(strict_types=1);

use App\Domain\Competition\LuckTable;

function luckTable(): LuckTable
{
    static $table = null;

    // api/tests/Unit/Competition -> repository root is four levels up.
    return $table ??= LuckTable::fromFile(dirname(__DIR__, 4).'/content/tables/luck-ppm.json');
}

it('is the table that the generator computes, so a stale file is caught', function () {
    expect(file_get_contents(dirname(__DIR__, 4).'/content/tables/luck-ppm.json'))->toBe(LuckTable::computedJson());
});

it('has the shape of the normal distribution: symmetric, rising, 1 sigma at the 84th percent and a tail of about 3.3 sigma', function () {
    $quantiles = LuckTable::compute();

    expect($quantiles)->toHaveCount(1_000)
        ->and($quantiles[0])->toBe(-$quantiles[999])
        ->and($quantiles[500])->toBe(-$quantiles[499])
        ->and($quantiles[841])->toBeBetween(990_000, 1_010_000)
        ->and(abs($quantiles[0]))->toBeBetween(3_200_000, 3_400_000)
        ->and($quantiles)->toBe(collect($quantiles)->sort()->values()->all());
});

it('scales the quantile by the width of the luck and rounds half away from zero', function () {
    $table = luckTable();

    // The top of the table is 3.29 sigma: with sigma 2% a run is at most 6.6% slower or faster.
    expect($table->luckPpm(999_999, 20_000))->toBe(65_811)
        ->and($table->luckPpm(0, 20_000))->toBe(-65_811)
        ->and($table->luckPpm(999_999, 0))->toBe(0)
        ->and($table->luckPpm(841_000, 20_000))->toBeBetween(19_800, 20_200)
        ->and($table->luckPpm(-5, 20_000))->toBe($table->luckPpm(0, 20_000))
        ->and($table->luckPpm(2_000_000, 20_000))->toBe($table->luckPpm(999_999, 20_000));
});

it('refuses a table of the wrong size', function () {
    expect(fn () => LuckTable::fromJson('{"quantile_ppm": [1, 2, 3]}'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => LuckTable::fromJson('not json'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => LuckTable::fromJson('{"quantile_ppm": "x"}'))->toThrow(InvalidArgumentException::class);
});
