<?php

declare(strict_types=1);

use App\Domain\Run\CarOnSurface;
use App\Domain\Run\Dynamics;
use App\Domain\Run\Section;
use App\Domain\Run\Thread;

it('adds up the sections to the length of the lap, a corner being its arc', function () {
    $thread = new Thread(Thread::CIRCUIT, [Section::straight(500), Section::corner(100, 90), Section::straight(500), Section::corner(100, 90)]);

    expect($thread->lengthM())->toEqualWithDelta(1000 + 2 * 100 * M_PI / 2, 1e-9);
});

it('says which surface a section is made of, and which surfaces the car is needed for', function () {
    $thread = new Thread(Thread::CIRCUIT, [Section::straight(100), Section::straight(100, 'gravel'), Section::corner(50, 90)], surface: 'asphalt');

    expect($thread->surfaceOf($thread->sections[0]))->toBe('asphalt')
        ->and($thread->surfaceOf($thread->sections[1]))->toBe('gravel')
        ->and($thread->surfaces())->toBe(['asphalt', 'gravel']);
});

it('keeps the order of the sections and knows a circuit from a run', function () {
    $sections = [Section::corner(30, 180, Section::LEFT), Section::straight(200)];

    expect((new Thread(Thread::RUN, $sections, 6.9))->isCircuit())->toBeFalse()
        ->and((new Thread(Thread::CIRCUIT, $sections))->sections[0]->turn)->toBe('left');
});

it('refuses what is nonsense', function (callable $make) {
    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'a straight of no length' => [fn () => Section::straight(0)],
    'a corner of no radius' => [fn () => Section::corner(0, 90)],
    'a corner of no angle' => [fn () => Section::corner(50, 0)],
    'a corner of more than a turn' => [fn () => Section::corner(50, 361)],
    'a corner that turns up' => [fn () => Section::corner(50, 90, 'up')],
    'a speed limit of nothing' => [fn () => Section::straight(100, null, 0.0)],
    'a rough stretch below nothing' => [fn () => Section::straight(100, null, null, -1.0)],
    'a surface that is not one' => [fn () => Section::straight(100, 'ice')],
    'a thread without sections' => [fn () => new Thread(Thread::CIRCUIT, [])],
    'a thread that is neither circuit nor run' => [fn () => new Thread('spiral', [Section::straight(100)])],
    'a way back that takes less than nothing' => [fn () => new Thread(Thread::RUN, [Section::straight(100)], -1.0)],
    'a car on a surface that does not exist' => [fn () => new CarOnSurface('ice', 100, 100, 100, 100, 100, 10)],
    'a car with a negative statistic' => [fn () => new CarOnSurface('asphalt_dry', -1, 100, 100, 100, 100, 10)],
    'dynamics with no power' => [fn () => new Dynamics(power: 0.0)],
    'dynamics that hold the top speed on mud at more than all of it' => [fn () => new Dynamics(topSpeedOnSurface: ['asphalt_dry' => 1.0, 'asphalt_wet' => 1.0, 'gravel_dry' => 1.0, 'mud' => 1.2, 'snow' => 1.0])],
]);

it('gives handling the most of a hairpin and the least of a wide bend', function () {
    $dynamics = new Dynamics;

    expect($dynamics->agility(1.0))->toEqualWithDelta(0.75, 0.01)
        ->and($dynamics->agility(95.0))->toEqualWithDelta(0.5, 1e-9)
        ->and($dynamics->agility(190.0))->toEqualWithDelta(0.25, 1e-9)
        ->and($dynamics->agility(1000.0))->toBe(0.25);
});
