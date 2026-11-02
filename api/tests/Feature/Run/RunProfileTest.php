<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Content\ThreadDocument;
use Illuminate\Support\Carbon;

// The picture of the lap of the run of the driver: the line of the thread of the track, the speed along it and the time of the lap at every point.

afterEach(fn () => Carbon::setTestNow());

/** The rules of the repository with the street driven on a thread of fourteen sections, in the app. */
function profileRules(): void
{
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true);
    $straight = static fn (int $length): array => ['type' => 'straight', 'length_m' => $length];
    $corner = static fn (int $radius, int $angle, string $turn = 'right'): array => ['type' => 'corner', 'radius_m' => $radius, 'angle_deg' => $angle, 'turn' => $turn];
    $thread = ['mode' => 'circuit', 'sections' => [
        $straight(629), $corner(70, 90), $straight(295), $corner(40, 90), $straight(206), $corner(90, 90), $straight(423), $corner(28, 180), $straight(256),
        $corner(80, 45, 'left'), $straight(29), $corner(80, 45), $straight(147), $corner(55, 90, 'left'),
    ]];
    $data['tracks']['zlomowo-street'] = [...$data['tracks']['zlomowo-street'], 'thread' => $thread, 'length_m' => ThreadDocument::lengthM($thread)];

    app()->instance(Rules::class, Rules::fromArray($data));
}

it('requires a token', function () {
    $this->getJson('/api/v1/runs/current/profile')->assertUnauthorized();
});

it('has nothing to draw for a track that has no thread', function () {
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true);
    unset($data['tracks']['zlomowo-street']['thread']);
    app()->instance(Rules::class, Rules::fromArray($data));
    [$driver] = driverWithRun();

    getRun($driver, 'current/profile')->assertOk()->assertExactJson(['data' => null]);
});

it('draws the line of the thread with the speed of the car and how far through the lap it is at every point', function () {
    profileRules();
    [$driver] = driverWithRun(['track_slug' => 'zlomowo-street']);

    $response = getRun($driver, 'current/profile')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $data = $response->json('data');

    expect($data['track'])->toBe('zlomowo-street')
        ->and($data['mode'])->toBe('circuit')
        ->and($data['length_m'])->toBe(2_599)
        ->and($data['weather'])->toBe('dry')
        ->and($data['lap_s'])->toEqualWithDelta(93.357, 0.01)
        ->and($data['drive_fraction'])->toEqual(1)
        ->and($data['sections'])->toHaveCount(14)
        ->and($data['sections'][0])->toEqual(['type' => 'straight', 'from_m' => 0, 'to_m' => 629])
        ->and($data['sections'][1]['type'])->toBe('corner')
        ->and($data['sections'][1]['radius_m'])->toEqual(70)
        ->and(count($data['points']))->toBe(count($data['speed_kmh']))
        ->and(count($data['points']))->toBe(count($data['time']))
        ->and(count($data['points']))->toBe(count($data['distance_m']))
        ->and($data['distance_m'][array_key_last($data['distance_m'])])->toEqualWithDelta(2_599.2, 0.2)
        ->and(count($data['points']))->toBeBetween(150, 220)
        ->and($data['time'][0])->toEqual(0)
        ->and($data['time'][array_key_last($data['time'])])->toEqual(1)
        ->and(max($data['speed_kmh']))->toBeLessThan(175.0)
        ->and(min($data['speed_kmh']))->toBeGreaterThan(40.0);

    // The line closes on itself, and the time only grows.
    expect($data['points'][0])->toEqual([0, 0])
        ->and($data['points'][array_key_last($data['points'])][0])->toEqualWithDelta(0.0, 0.2)
        ->and($data['points'][array_key_last($data['points'])][1])->toEqualWithDelta(0.0, 0.2);

    foreach ($data['time'] as $index => $time) {
        if ($index > 0) {
            expect($time)->toBeGreaterThanOrEqual($data['time'][$index - 1]);
        }
    }
});

it('draws it for the weather that is asked for: slower in the rain', function () {
    profileRules();
    [$driver] = driverWithRun(['track_slug' => 'zlomowo-street']);

    $dry = getRun($driver, 'current/profile?weather=dry')->assertOk()->json('data.lap_s');
    $rain = getRun($driver, 'current/profile?weather=rain')->assertOk()->json('data');

    expect($rain['weather'])->toBe('rain')
        ->and($rain['lap_s'])->toBeGreaterThan($dry + 3.0)
        ->and($rain['lap_s'])->toEqualWithDelta(98.195, 0.01);

    getRun($driver, 'current/profile?weather=hail')->assertUnprocessable();
});
