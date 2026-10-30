<?php

declare(strict_types=1);

use App\Domain\Run\CarOnSurface;
use App\Domain\Run\Dynamics;
use App\Domain\Run\LapSimulator;
use App\Domain\Run\Motion;
use App\Domain\Run\Section;
use App\Domain\Run\Thread;

/*
| The reference numbers below were made by the prototype of the model on the same inputs (an independent port of it, in Python, written before this class
| and run on the real statistics of the cars of the game), so they pin that this class does what the proposal says it does.
*/

/** The statistics of three cars of the game on a surface, with the tires and the drive already multiplied in. */
function lapCar(string $name, string $surface = 'asphalt_dry'): CarOnSurface
{
    $stats = [
        'rdzak-gt-4' => [
            'asphalt_dry' => [97.0, 175.0, 90.0, 88.0, 90.0, 20.0],
            'asphalt_wet' => [97.0, 175.0, 76.5, 74.8, 76.5, 20.0],
            'gravel_dry' => [97.0, 175.0, 54.0, 52.8, 54.0, 20.0],
        ],
        'bizon-m-2' => [
            'asphalt_dry' => [147.61310223266744, 200.0, 92.78350515463916, 77.23151500076303, 73.3354136436143, 15.0],
        ],
        'orzel-v12' => [
            'asphalt_dry' => [333.7834623617425, 345.0, 92.78350515463916, 71.06933154128166, 67.4840941020458, 10.0],
        ],
    ];

    return new CarOnSurface($surface, ...$stats[$name][$surface]);
}

/** @param  array<string, float>  $changes */
function lapCarWith(array $changes, string $surface = 'asphalt_dry'): CarOnSurface
{
    $base = lapCar('rdzak-gt-4');
    $values = array_merge(
        ['accel' => $base->accel, 'topSpeedKmh' => $base->topSpeedKmh, 'grip' => $base->grip, 'handling' => $base->handling, 'braking' => $base->braking, 'clearance' => $base->clearance],
        $changes,
    );

    return new CarOnSurface($surface, ...$values);
}

/** The street of the proposal: a city circuit of fourteen sections, 2 599 m. */
function lapStreet(): Thread
{
    return new Thread(Thread::CIRCUIT, [
        Section::straight(629), Section::corner(70, 90), Section::straight(295), Section::corner(40, 90), Section::straight(206), Section::corner(90, 90),
        Section::straight(423), Section::corner(28, 180), Section::straight(256), Section::corner(80, 45, Section::LEFT), Section::straight(29), Section::corner(80, 45),
        Section::straight(147), Section::corner(55, 90, Section::LEFT),
    ]);
}

function lapSwitchbacks(): Thread
{
    return new Thread(Thread::CIRCUIT, [
        Section::straight(247), Section::corner(28, 160), Section::straight(180), Section::corner(30, 170), Section::straight(285), Section::corner(28, 160),
        Section::straight(161), Section::corner(32, 150), Section::straight(313), Section::corner(34, 120), Section::straight(228), Section::corner(45, 100),
        Section::straight(342), Section::corner(30, 170), Section::straight(237), Section::corner(38, 130), Section::straight(190), Section::corner(60, 60),
    ]);
}

function lapDrag(float $returnSeconds = 6.9): Thread
{
    return new Thread(Thread::RUN, [Section::straight(1600)], $returnSeconds);
}

/** @return array<string, CarOnSurface> */
function lapCars(string $name): array
{
    return ['asphalt' => lapCar($name)];
}

it('drives the street the way the reference model does', function (string $car, float $seconds, float $accelerating, float $braking, float $cornering, float $peakKmh) {
    $lap = (new LapSimulator)->simulate(lapStreet(), lapCars($car));

    expect($lap->seconds())->toEqualWithDelta($seconds, 1e-5)
        ->and($lap->split()['accelerating'])->toEqualWithDelta($accelerating, 1e-5)
        ->and($lap->split()['braking'])->toEqualWithDelta($braking, 1e-5)
        ->and($lap->split()['cornering'])->toEqualWithDelta($cornering, 1e-5)
        ->and($lap->split()['holding'])->toBe(0.0)
        ->and($lap->peakKmh())->toEqualWithDelta($peakKmh, 1e-4)
        ->and($lap->lengthM())->toEqualWithDelta(2599.181363777, 1e-6);
})->with([
    'Rdzak GT-4' => ['rdzak-gt-4', 93.356894512, 56.046974925, 9.398377087, 27.9115425, 148.604685151],
    'Bizon M-2' => ['bizon-m-2', 90.874990647, 47.130273963, 14.918062421, 28.826654263, 166.32874848],
    'Orzeł V12' => ['orzel-v12', 84.434646908, 30.40487591, 24.487021143, 29.542749855, 215.683922474],
]);

it('drives the drag strip from a standstill, and adds the way back', function (string $car, float $drive, float $peakKmh) {
    $lap = (new LapSimulator)->simulate(lapDrag(), lapCars($car));

    expect($lap->seconds())->toEqualWithDelta($drive + 6.9, 1e-5)
        ->and($lap->peakKmh())->toEqualWithDelta($peakKmh, 1e-4)
        ->and($lap->sections[0]->entry)->toBe(0.0)
        ->and($lap->split()['return'])->toBe(6.9);
})->with([
    'Rdzak GT-4' => ['rdzak-gt-4', 42.610612492, 169.708800246],
    'Bizon M-2' => ['bizon-m-2', 37.431922617, 194.231173455],
    'Orzeł V12' => ['orzel-v12', 27.260795416, 300.708137784],
]);

it('drives the switchbacks, the rain, a mixed surface and a speed limit as the reference model does', function () {
    $simulator = new LapSimulator;

    $switchbacks = $simulator->simulate(lapSwitchbacks(), lapCars('rdzak-gt-4'));
    $rain = $simulator->simulate(lapStreet(), ['asphalt' => lapCar('rdzak-gt-4', 'asphalt_wet')]);
    $rally = $simulator->simulate(new Thread(Thread::CIRCUIT, [
        Section::straight(400), Section::corner(50, 90), Section::straight(200, 'gravel', null, 40.0), Section::corner(30, 120, Section::RIGHT, 'gravel', null, 30.0), Section::straight(300), Section::corner(60, 90),
    ]), ['asphalt' => lapCar('rdzak-gt-4'), 'gravel' => lapCar('rdzak-gt-4', 'gravel_dry')]);
    $limited = $simulator->simulate(new Thread(Thread::CIRCUIT, [Section::straight(600, null, 70.0), Section::corner(60, 180), Section::straight(600), Section::corner(60, 180)]), lapCars('rdzak-gt-4'));

    expect($switchbacks->seconds())->toEqualWithDelta(127.280062294, 1e-5)
        ->and($switchbacks->averageKmh())->toEqualWithDelta(82.023314068, 1e-5)
        ->and($rain->seconds())->toEqualWithDelta(98.195017484, 1e-5)
        ->and($rally->seconds())->toEqualWithDelta(49.327476872, 1e-5)
        ->and($limited->seconds())->toEqualWithDelta(65.779728523, 1e-5)
        ->and($limited->split()['holding'])->toEqualWithDelta(30.857142857, 1e-6);
});

it('gives the closed forms of the motion that the prototype gave', function () {
    $motion = new Motion(lapCar('rdzak-gt-4'), new Dynamics);

    expect($motion->launchEnd)->toEqualWithDelta(6.918848769099725, 1e-9)
        ->and($motion->speedAfter(0.0, 400.0))->toEqualWithDelta(37.38222243755737, 1e-9)
        ->and($motion->timeFor(0.0, 400.0))->toEqualWithDelta(15.490864565540827, 1e-6)
        ->and($motion->distanceTo(0.0, 27.78))->toEqualWithDelta(136.85960770942933, 1e-9)
        ->and($motion->cornerSpeed(60.0))->toEqualWithDelta(22.28454446280624, 1e-9)
        ->and($motion->cornerSpeed(15.0))->toEqualWithDelta(11.127455853648197, 1e-9)
        ->and($motion->cornerSpeed(250.0))->toEqualWithDelta(45.66333068122413, 1e-9);
});

it('agrees with a step by step integration of the same acceleration', function () {
    $motion = new Motion(lapCar('rdzak-gt-4'), new Dynamics);
    $drag = $motion->rho / $motion->topSpeed ** 3;
    $speed = 0.0;
    $distance = 0.0;
    $time = 0.0;
    $step = 0.0005;

    while ($distance < 400.0) {
        $acceleration = $speed > 0 ? min($motion->launch, $motion->rho / $speed - $drag * $speed * $speed) : $motion->launch;
        $speed += $acceleration * $step;
        $distance += $speed * $step;
        $time += $step;
    }

    expect($motion->timeFor(0.0, 400.0))->toEqualWithDelta($time, $time * 0.005)
        ->and($motion->speedAfter(0.0, 400.0))->toEqualWithDelta($speed, $speed * 0.005)
        ->and($motion->distanceTo(0.0, $speed))->toEqualWithDelta(400.0, 3.0);
});

it('enters a corner at the speed of the corner, braking over the distance the brakes need', function () {
    $thread = new Thread(Thread::RUN, [Section::straight(900), Section::corner(25, 180)]);
    $lap = (new LapSimulator)->simulate($thread, lapCars('rdzak-gt-4'));
    $straight = $lap->sections[0];
    $motion = $straight->motion;

    expect($straight->exit)->toEqualWithDelta($motion->cornerSpeed(25.0), 1e-9)
        ->and($straight->peak)->toBeGreaterThan($straight->exit)
        ->and($straight->section->lengthM - $straight->accelerateM - $straight->holdM)
        ->toEqualWithDelta(($straight->peak ** 2 - $straight->exit ** 2) / (2 * $motion->brake), 1e-6)
        ->and($lap->sections[1]->entry)->toBe($straight->exit);
});

it('drives a circuit as a steady state: it leaves the line at the speed it crossed it', function () {
    $lap = (new LapSimulator)->simulate(lapStreet(), lapCars('rdzak-gt-4'));

    expect($lap->sections[0]->entry)->toBe($lap->sections[array_key_last($lap->sections)]->exit)
        ->and($lap->sections[0]->entry)->toBeGreaterThan(0.0);
});

it('does not take longer for a car that is better in any one statistic', function (string $statistic) {
    $simulator = new LapSimulator;
    $base = lapCar('rdzak-gt-4');

    foreach ([lapStreet(), lapSwitchbacks(), lapDrag()] as $thread) {
        $slower = $simulator->simulate($thread, ['asphalt' => $base])->seconds();
        $better = $simulator->simulate($thread, ['asphalt' => lapCarWith([$statistic => $base->{$statistic} * 1.1])])->seconds();

        expect($better)->toBeLessThanOrEqual($slower + 1e-9);
    }
})->with(['accel', 'topSpeedKmh', 'grip', 'handling', 'braking']);

it('is slower on the surfaces that hold the top speed back, the worse the more they hold it', function () {
    $thread = new Thread(Thread::CIRCUIT, [Section::straight(2000), Section::corner(150, 180), Section::straight(2000), Section::corner(150, 180)]);
    $times = [];

    foreach (['asphalt_dry', 'gravel_dry', 'snow', 'mud'] as $surface) {
        $times[$surface] = (new LapSimulator)->simulate($thread, ['asphalt' => lapCarWith([], $surface)])->seconds();
    }

    expect($times['asphalt_dry'])->toBeLessThan($times['gravel_dry'])
        ->and($times['gravel_dry'])->toBeLessThan($times['snow'])
        ->and($times['snow'])->toBeLessThan($times['mud']);
});

it('never goes faster than a speed limit', function () {
    $thread = new Thread(Thread::CIRCUIT, [Section::straight(800, null, 50.0), Section::corner(100, 180), Section::straight(800), Section::corner(100, 180)]);
    $lap = (new LapSimulator)->simulate($thread, lapCars('orzel-v12'));

    expect($lap->sections[0]->peak)->toBeLessThanOrEqual(50.0 / 3.6 + 1e-9)
        ->and($lap->sections[0]->holdSeconds)->toBeGreaterThan(0.0)
        ->and($lap->sections[2]->peak)->toBeGreaterThan(50.0 / 3.6);
});

it('holds a rough stretch to a speed that the clearance of the car decides, and asks nothing of a car on a smooth one', function () {
    $smooth = new Thread(Thread::CIRCUIT, [Section::straight(600), Section::corner(80, 180), Section::straight(600), Section::corner(80, 180)]);
    $rough = new Thread(Thread::CIRCUIT, [Section::straight(600, null, null, 60.0), Section::corner(80, 180), Section::straight(600, null, null, 60.0), Section::corner(80, 180)]);
    $simulator = new LapSimulator;
    $lowCar = ['asphalt' => lapCarWith(['clearance' => 20.0])];
    $highCar = ['asphalt' => lapCarWith(['clearance' => 60.0])];

    $onRoughHigh = $simulator->simulate($rough, $highCar);
    $onRoughLow = $simulator->simulate($rough, $lowCar);

    expect($simulator->simulate($smooth, $highCar)->seconds())->toBe($simulator->simulate($smooth, $lowCar)->seconds())
        ->and($onRoughHigh->sections[0]->peak)->toBeLessThanOrEqual(90.0 / 3.6 + 1e-9)
        ->and($onRoughLow->sections[0]->peak)->toBeLessThanOrEqual(90.0 / 3.6 * sqrt(20.0 / 60.0) + 1e-9)
        ->and($onRoughLow->seconds())->toBeGreaterThan($onRoughHigh->seconds() + 1.0)
        ->and($onRoughHigh->seconds())->toBeGreaterThan($simulator->simulate($smooth, $highCar)->seconds());
});

it('gives the same lap every time', function () {
    $first = (new LapSimulator)->simulate(lapSwitchbacks(), lapCars('bizon-m-2'));
    $second = (new LapSimulator)->simulate(lapSwitchbacks(), lapCars('bizon-m-2'));

    expect($second->seconds())->toBe($first->seconds())
        ->and($second->lapMs())->toBe($first->lapMs())
        ->and($first->lapMs())->toBe((int) round($first->seconds() * 1000));
});

it('makes a picture of the lap that adds up to the lap', function () {
    $lap = (new LapSimulator)->simulate(lapStreet(), lapCars('rdzak-gt-4'));
    $samples = $lap->samples(10.0);
    $last = $samples[array_key_last($samples)];

    expect($samples[0]['distance_m'])->toBe(0.0)
        ->and($last['distance_m'])->toEqualWithDelta($lap->lengthM(), 1e-6)
        ->and($last['elapsed_s'])->toEqualWithDelta($lap->seconds(), $lap->seconds() * 0.01);

    foreach ($samples as $index => $sample) {
        expect($sample['speed_kmh'])->toBeGreaterThan(0.0)->toBeLessThanOrEqual(175.0);

        if ($index > 0) {
            expect($sample['distance_m'])->toBeGreaterThanOrEqual($samples[$index - 1]['distance_m'])
                ->and($sample['elapsed_s'])->toBeGreaterThanOrEqual($samples[$index - 1]['elapsed_s']);
        }
    }
});

it('does not divide by nothing for a car with nothing', function () {
    $lap = (new LapSimulator)->simulate(lapStreet(), ['asphalt' => new CarOnSurface('asphalt_dry', 0.0, 0.0, 0.0, 0.0, 0.0, 0.0)]);

    expect(is_finite($lap->seconds()))->toBeTrue()
        ->and($lap->seconds())->toBeGreaterThan(93.0);
});

it('wants a car for every surface the thread is made of', function () {
    $thread = new Thread(Thread::CIRCUIT, [Section::straight(300), Section::corner(50, 180, Section::RIGHT, 'gravel'), Section::straight(300), Section::corner(50, 180)]);

    expect(fn () => (new LapSimulator)->simulate($thread, lapCars('rdzak-gt-4')))->toThrow(InvalidArgumentException::class, 'gravel');
});

it('is quick enough to be solved when a run is frozen, for every weather', function () {
    $simulator = new LapSimulator;
    $cars = lapCars('orzel-v12');
    $thread = lapSwitchbacks();
    $start = microtime(true);

    for ($lap = 0; $lap < 200; $lap++) {
        $simulator->simulate($thread, $cars);
    }

    // The budget is 0.3 ms a lap on the machine of the tests, ten times that to leave room for a slow one.
    expect((microtime(true) - $start) / 200)->toBeLessThan(0.003);
});
