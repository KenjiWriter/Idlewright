<?php

declare(strict_types=1);

use App\Domain\Settlement\TournamentPayout;

it('is the same split as for a full bracket when every place is held by a real player', function (int $size, int $fee) {
    $full = TournamentPayout::split($size, $fee);
    $among = TournamentPayout::splitAmong($size, $size, $fee);

    expect($among->totalCents)->toBe($full->totalCents)
        ->and($among->poolCents)->toBe($full->poolCents)
        ->and($among->rakeCents)->toBe($full->rakeCents)
        ->and($among->groups)->toBe($full->groups)
        ->and($among->remainderCents)->toBe($full->remainderCents);
})->with([[8, 100_000], [16, 80_000], [32, 80_000], [32, 33_300], [16, 48_001]]);

it('takes the pool from the real players only: ten real players in a bracket of 16 at $480', function () {
    $result = TournamentPayout::splitAmong(10, 16, 48_000);

    // 10 x 480 = 4 800, less 10% = 4 320. The eight paying places are all taken by real players.
    expect($result->totalCents)->toBe(480_000)
        ->and($result->poolCents)->toBe(432_000)
        ->and($result->rakeCents)->toBe(48_000)
        ->and(array_column($result->groups, 'each_cents'))->toBe([172_800, 86_400, 43_200, 21_600])
        ->and($result->perPlace())->toHaveCount(8)
        ->and($result->distributedCents())->toBe(432_000);
});

it('scales the shares of the places that real players hold up to the whole pool when there are fewer of them than paying places', function () {
    // Three real players in a bracket of 8: places 1, 2 and 3 (50%, 25%, 12.5%) share the pool in the proportion 4 : 2 : 1.
    $result = TournamentPayout::splitAmong(3, 8, 100_000);

    expect($result->poolCents)->toBe(270_000)
        ->and($result->perPlace())->toBe([154_287, 77_142, 38_571])
        ->and($result->distributedCents())->toBe(270_000)
        ->and($result->groups)->toBe([
            ['stage' => 'winner', 'count' => 1, 'each_cents' => 154_285],
            ['stage' => 'finalist', 'count' => 1, 'each_cents' => 77_142],
            ['stage' => 'semifinalist', 'count' => 1, 'each_cents' => 38_571],
        ]);
});

it('gives the whole pool to the winner when only one real player is in', function () {
    $result = TournamentPayout::splitAmong(1, 8, 100_000);

    expect($result->perPlace())->toBe([90_000])
        ->and($result->rakeCents)->toBe(10_000);
});

it('never pays out more or less than the pool, whatever the number of real players, the size and the fee', function () {
    foreach ([8, 16, 32] as $size) {
        foreach ([1, 2, 3, 5, 7, 8, 9, 13, 16, 17, 24, 32] as $real) {
            if ($real > $size) {
                continue;
            }

            foreach ([33_300, 48_000, 80_000, 144_001] as $fee) {
                $result = TournamentPayout::splitAmong($real, $size, $fee);

                expect($result->distributedCents())->toBe($result->poolCents, "size {$size}, real {$real}, fee {$fee}")
                    ->and($result->totalCents)->toBe($result->poolCents + $result->rakeCents)
                    ->and($result->remainderCents)->toBeLessThan(count($result->perPlace()));
            }
        }
    }
});

it('refuses what has no table or no real player', function () {
    expect(fn () => TournamentPayout::splitAmong(3, 12, 100_000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TournamentPayout::splitAmong(0, 8, 100_000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TournamentPayout::splitAmong(9, 8, 100_000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TournamentPayout::splitAmong(3, 8, 0))->toThrow(InvalidArgumentException::class);
});
