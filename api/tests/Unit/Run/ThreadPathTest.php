<?php

declare(strict_types=1);

use App\Domain\Run\Section;
use App\Domain\Run\Thread;
use App\Domain\Run\ThreadPath;

function pathSquare(string $turn = Section::RIGHT): Thread
{
    return new Thread(Thread::CIRCUIT, [
        Section::straight(100), Section::corner(50, 90, $turn), Section::straight(100), Section::corner(50, 90, $turn),
        Section::straight(100), Section::corner(50, 90, $turn), Section::straight(100), Section::corner(50, 90, $turn),
    ]);
}

it('draws a rounded square that closes on itself, and says it needed no bending', function () {
    $path = ThreadPath::outline(pathSquare());

    expect($path['gap_m'])->toEqualWithDelta(0.0, 1e-9)
        ->and($path['turn_deg'])->toBe(-360.0)
        ->and($path['points'][0])->toBe(['x' => 0.0, 'y' => 0.0, 'distance_m' => 0.0, 'section' => 0]);

    $last = $path['points'][array_key_last($path['points'])];

    expect($last['x'])->toEqualWithDelta(0.0, 1e-6)
        ->and($last['y'])->toEqualWithDelta(0.0, 1e-6)
        ->and($last['distance_m'])->toEqualWithDelta(pathSquare()->lengthM(), 1e-6);
});

it('turns the other way for a left turn, and the same way round the other side of the line', function () {
    $right = ThreadPath::outline(pathSquare(Section::RIGHT));
    $left = ThreadPath::outline(pathSquare(Section::LEFT));

    expect($left['turn_deg'])->toBe(360.0)
        ->and($left['points'][12]['y'])->toEqualWithDelta(-$right['points'][12]['y'], 1e-9)
        ->and($left['points'][12]['x'])->toEqualWithDelta($right['points'][12]['x'], 1e-9);
});

it('bends a loop that does not close until it does, and tells the gap it had', function () {
    $open = new Thread(Thread::CIRCUIT, [Section::straight(300), Section::corner(50, 90), Section::straight(300), Section::corner(50, 90)]);
    $path = ThreadPath::outline($open);
    $last = $path['points'][array_key_last($path['points'])];

    expect($path['gap_m'])->toBeGreaterThan(100.0)
        ->and($last['x'])->toEqualWithDelta(0.0, 1e-6)
        ->and($last['y'])->toEqualWithDelta(0.0, 1e-6);
});

it('does not bend a run, which has a start and a finish', function () {
    $run = new Thread(Thread::RUN, [Section::straight(500), Section::corner(40, 90), Section::straight(200)]);
    $path = ThreadPath::outline($run);
    $last = $path['points'][array_key_last($path['points'])];

    expect($path['gap_m'])->toBe(0.0)
        ->and($last['x'])->toEqualWithDelta(500 + 40, 1e-6)
        ->and($last['y'])->toEqualWithDelta(-(40 + 200), 1e-6);
});

it('puts a point about every step along the line, the ends of the sections among them, with the section each belongs to', function () {
    $path = ThreadPath::outline(pathSquare(), 10.0);
    $distances = array_column($path['points'], 'distance_m');
    $sections = array_column($path['points'], 'section');

    expect(count($path['points']))->toBeGreaterThan(40)
        ->and(max($sections))->toBe(7)
        ->and($sections[1])->toBe(0);

    for ($index = 1; $index < count($distances); $index++) {
        expect($distances[$index])->toBeGreaterThan($distances[$index - 1]);
    }
});
