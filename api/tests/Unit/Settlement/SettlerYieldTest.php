<?php

declare(strict_types=1);

use App\Domain\Settlement\BonusWindow;
use App\Domain\Settlement\Ppm;
use App\Domain\Settlement\RunConfig;
use App\Domain\Settlement\RunState;
use App\Domain\Settlement\Settler;

// The yield of a car: a car in a slot that the game pays less for gets a part of what each lap pays, cash and experience. The car of these tests laps in 75 s for $15.50 and 25 XP, uses
// 1.5 l a lap and has a tank of 45 l, which is 30 laps (2 250 000 ms).

const YIELD_LAP_MS = 75_000;
const YIELD_TANK_MS = 2_250_000;
const YIELD_TANK_ML = 45_000;

function yieldConfig(): RunConfig
{
    return RunConfig::constant(YIELD_LAP_MS, 1_550, 25, 1_500);
}

it('pays a part of every lap, rounded lap by lap, and leaves the laps and the fuel as they were', function () {
    $whole = (new Settler)->settle(new RunState(0, 0, YIELD_TANK_ML), YIELD_TANK_MS, yieldConfig());
    $part = (new Settler)->settle(new RunState(0, 0, YIELD_TANK_ML), YIELD_TANK_MS, yieldConfig(), null, [], 700_000);

    expect($part->laps)->toBe(30)->and($part->state->fuelMl)->toBe($whole->state->fuelMl)->and($part->state->cursorMs)->toBe($whole->state->cursorMs)
        ->and($part->cashCents)->toBe(30 * Ppm::mul(1_550, 700_000))->and($part->xp)->toBe(30 * Ppm::mul(25, 700_000))
        ->and($part->cashCents)->toBeLessThan($whole->cashCents);
});

it('pays all of it by default, and for a yield of 100%', function () {
    $default = (new Settler)->settle(new RunState(0, 0, YIELD_TANK_ML), YIELD_TANK_MS, yieldConfig());
    $whole = (new Settler)->settle(new RunState(0, 0, YIELD_TANK_ML), YIELD_TANK_MS, yieldConfig(), null, [], Ppm::ONE);

    expect($whole->cashCents)->toBe($default->cashCents)->and($whole->cashCents)->toBe(30 * 1_550)->and($whole->xp)->toBe(30 * 25);
});

it('pays nothing for a yield of nothing, and still drives the car', function () {
    $result = (new Settler)->settle(new RunState(0, 0, YIELD_TANK_ML), YIELD_TANK_MS, yieldConfig(), null, [], 0);

    expect($result->laps)->toBe(30)->and($result->cashCents)->toBe(0)->and($result->xp)->toBe(0);
});

it('pays in steps what it pays at once', function () {
    $once = (new Settler)->settleInChunks(new RunState(0, 0, YIELD_TANK_ML), YIELD_TANK_MS, yieldConfig(), 10 * YIELD_LAP_MS * 3, null, [], 333_333);
    $steps = (new Settler)->settleInChunks(new RunState(0, 0, YIELD_TANK_ML), YIELD_TANK_MS, yieldConfig(), 7 * YIELD_LAP_MS + 13, null, [], 333_333);

    $state = new RunState(0, 0, YIELD_TANK_ML);
    $cash = 0;
    $xp = 0;

    foreach ([300_000, 1_000_001, 1_500_000, YIELD_TANK_MS] as $until) {
        $step = (new Settler)->settle($state, $until, yieldConfig(), null, [], 333_333);
        $state = $step->state;
        $cash += $step->cashCents;
        $xp += $step->xp;
    }

    expect($steps->cashCents)->toBe($once->cashCents)->and($steps->xp)->toBe($once->xp)->and($cash)->toBe($once->cashCents)->and($xp)->toBe($once->xp)
        ->and($once->cashCents)->toBe(30 * Ppm::mul(1_550, 333_333));
});

it('takes the bonus of the VIP account from what is left, not from the whole pay', function () {
    $window = new BonusWindow(0, PHP_INT_MAX, 200_000, 200_000);
    $result = (new Settler)->settle(new RunState(0, 0, YIELD_TANK_ML), YIELD_TANK_MS, yieldConfig(), null, [$window], 500_000);

    // $15.50 at half is $7.75, and 20% of that is $1.55; 25 XP at half is 13 (12.5 rounded), and 20% of that is 3.
    $lapCash = Ppm::mul(1_550, 500_000);
    $lapXp = Ppm::mul(25, 500_000);

    expect($result->bonusCashCents)->toBe(30 * Ppm::mul($lapCash, 200_000))->and($result->bonusXp)->toBe(30 * Ppm::mul($lapXp, 200_000))
        ->and($result->cashCents)->toBe(30 * ($lapCash + Ppm::mul($lapCash, 200_000)));
});
