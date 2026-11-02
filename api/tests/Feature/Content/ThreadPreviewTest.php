<?php

declare(strict_types=1);

use App\Content\Catalog;
use App\Content\ThreadDocument;
use App\Content\ThreadPreview;

// The numbers a person is shown while a thread is drawn: the reference car on it, and the cars of the catalog on it and by the formula.

/** @return array<string, mixed> the city street of the proposal: fourteen sections, a circuit */
function previewStreetThread(): array
{
    $straight = static fn (int $length): array => ['type' => 'straight', 'length_m' => $length];
    $corner = static fn (int $radius, int $angle, string $turn = 'right'): array => ['type' => 'corner', 'radius_m' => $radius, 'angle_deg' => $angle, 'turn' => $turn];

    return ['mode' => 'circuit', 'sections' => [
        $straight(629), $corner(70, 90), $straight(295), $corner(40, 90), $straight(206), $corner(90, 90), $straight(423), $corner(28, 180), $straight(256),
        $corner(80, 45, 'left'), $straight(29), $corner(80, 45), $straight(147), $corner(55, 90, 'left'),
    ]];
}

/** @return array<string, mixed> the rules of the repository with the street driven on its thread (the base lap said by hand: 75 s) */
function previewRules(array $track = []): array
{
    $rules = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
    $thread = previewStreetThread();
    $rules['tracks']['zlomowo-street'] = [
        ...$rules['tracks']['zlomowo-street'],
        'lap_from' => 'thread',
        'thread' => $thread,
        'length_m' => ThreadDocument::lengthM($thread),
        'base_lap_ms' => 75_000,
        ...$track,
    ];

    return $rules;
}

function threadPreview(): ThreadPreview
{
    return new ThreadPreview(app(Catalog::class));
}

it('shows the length, the line and how long the reference car takes on the thread', function () {
    $preview = threadPreview()->of(previewRules(), 'zlomowo-street', ['rdzak-gt-4']);

    expect($preview['length_m'])->toBe(2_599)
        ->and($preview['sections'])->toBe(14)
        ->and($preview['reference_s'])->toEqualWithDelta(93.356894512, 1e-5)
        ->and($preview['base_lap_ms'])->toBe(75_000)
        ->and($preview['pace'])->toEqualWithDelta(75.0 / 93.356894512, 1e-6)
        ->and($preview['gap_m'])->toBeGreaterThan(100.0)
        ->and($preview['turn_deg'])->toBe(-360.0)
        ->and($preview['outline'][0])->toMatchArray(['x' => 0.0, 'y' => 0.0, 'distance_m' => 0.0, 'section' => 0])
        ->and($preview['outline'][0]['speed_kmh'])->toBeGreaterThan(0.0)
        ->and($preview['peak_kmh'])->toBeGreaterThan(140.0)
        ->and($preview['reference_split']['cornering'])->toBeGreaterThan(20.0);
});

it('drives the cars on the thread and by the formula, and says how far apart the two are', function () {
    $preview = threadPreview()->of(previewRules(), 'zlomowo-street', ['rdzak-gt-4', 'bizon-m-2', 'orzel-v12']);
    $rows = array_column($preview['cars'], null, 'slug');

    expect($rows['rdzak-gt-4']['thread_ms'])->toBe(75_000)
        ->and($rows['rdzak-gt-4']['formula_ms'])->toBe(75_000)
        ->and($rows['rdzak-gt-4']['delta_percent'])->toBe(0.0)
        ->and($rows['rdzak-gt-4']['physical_s'])->toEqualWithDelta(93.356894512, 1e-5)
        ->and($rows['bizon-m-2']['formula_ms'])->toBe(69_004)
        ->and($rows['bizon-m-2']['thread_ms'])->toBeBetween(73_005, 73_007)
        ->and($rows['bizon-m-2']['delta_percent'])->toEqualWithDelta(100 * (73_006 - 69_004) / 69_004, 0.01)
        ->and($rows['orzel-v12']['thread_ms'])->toBeBetween(67_831, 67_833)
        ->and($rows['orzel-v12']['formula_ms'])->toBe(65_027)
        ->and($rows['orzel-v12']['peak_kmh'])->toBeGreaterThan($rows['rdzak-gt-4']['peak_kmh'])
        ->and(array_keys($rows['orzel-v12']['split']))->toBe(['accelerating', 'holding', 'braking', 'cornering', 'return']);
});

it('makes a lap for a thread of a track that says no base lap: the lap of the reference car on it', function () {
    $rules = previewRules();
    unset($rules['tracks']['zlomowo-street']['base_lap_ms'], $rules['tracks']['zlomowo-street']['speed_kmh']);
    $preview = threadPreview()->of($rules, 'zlomowo-street', ['rdzak-gt-4']);

    expect($preview['base_lap_ms'])->toBeNull()
        ->and($preview['pace'])->toBeNull()
        ->and($preview['cars'][0]['thread_ms'])->toBe((int) round(93.356894512 * 1000))
        ->and(threadPreview()->speedKmh($rules, 'zlomowo-street'))->toBe(round(2_599 * 3.6 / 93.356894512, 4));
});

it('says nothing of a track that has no thread, or one that cannot be driven, and drives only cars that burn fuel', function () {
    $broken = previewRules();
    unset($broken['tracks']['zlomowo-street']['thread']['sections'][1]['radius_m']);

    $without = previewRules();
    unset($without['tracks']['zlomowo-yard-dash']['thread']);

    expect(threadPreview()->of($without, 'zlomowo-yard-dash', ['rdzak-gt-4']))->toBeNull()
        ->and(threadPreview()->of($broken, 'zlomowo-street', ['rdzak-gt-4']))->toBeNull()
        ->and(threadPreview()->of(previewRules(), 'zlomowo-street', ['rdzak-gt-4', 'no-such-car'])['cars'])->toHaveCount(1);
});
