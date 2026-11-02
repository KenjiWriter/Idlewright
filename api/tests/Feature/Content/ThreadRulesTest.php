<?php

declare(strict_types=1);

use App\Content\Catalog;
use App\Content\Definitions\WorldForm;
use App\Content\PackValidator;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Content\ThreadDocument;
use App\Domain\Run\Dynamics;
use App\Domain\Run\RunFreezer;

// A track can say its lap is driven on its thread: the reference car laps exactly the base lap of the track, the other cars by what the model says, and a track that
// does not say it is driven as it was.

/** @return array<string, mixed> the rules of the repository */
function threadRules(): array
{
    return json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> the city street of the proposal: fourteen sections, a circuit */
function streetThread(): array
{
    $straight = static fn (int $length): array => ['type' => 'straight', 'length_m' => $length];
    $corner = static fn (int $radius, int $angle, string $turn = 'right'): array => ['type' => 'corner', 'radius_m' => $radius, 'angle_deg' => $angle, 'turn' => $turn];

    return ['mode' => 'circuit', 'sections' => [
        $straight(629), $corner(70, 90), $straight(295), $corner(40, 90), $straight(206), $corner(90, 90), $straight(423), $corner(28, 180), $straight(256),
        $corner(80, 45, 'left'), $straight(29), $corner(80, 45), $straight(147), $corner(55, 90, 'left'),
    ]];
}

/**
 * The rules with a track whose lap is driven on a thread. The base lap is said by hand (75 s), so the numbers of the track are the ones of the repository whatever the
 * thread comes to.
 *
 * @param  array<string, mixed>  $thread
 * @param  array<string, mixed>  $track  what the track says over it
 * @param  array<string, mixed>|null  $rules  the rules to change: the ones of the repository when none are given
 * @return array<string, mixed>
 */
function rulesWithThread(?array $thread = null, array $track = [], string $slug = 'zlomowo-street', ?array $rules = null): array
{
    $rules ??= threadRules();
    $thread ??= streetThread();
    // A thread that cannot be driven has no length: the track keeps its own, and the validator says what is wrong.
    try {
        $length = ThreadDocument::lengthM($thread, $rules['tracks'][$slug]['surface']);
    } catch (InvalidArgumentException) {
        $length = $rules['tracks'][$slug]['length_m'];
    }

    $rules['tracks'][$slug] = [
        ...$rules['tracks'][$slug],
        'lap_from' => 'thread',
        'thread' => $thread,
        'length_m' => $length,
        'base_lap_ms' => 75_000,
        ...$track,
    ];

    return $rules;
}

/** @param  array<string, mixed>  $rules */
function frozenOnThread(array $rules, string $car, string $track = 'zlomowo-street'): array
{
    return (new RunFreezer)->freeze((new RunInputsFactory(app(Catalog::class), Rules::fromArray($rules)))->make($car, $track), 'seed', 0)->config;
}

/**
 * What the validator says of the rules of the repository after a change: the pointers of its errors and of its warnings.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>  $change
 * @return array{errors: list<string>, warnings: list<string>}
 */
function threadValidated(Closure $change): array
{
    $result = PackValidator::fromConfig()->validateSource(contentWith(['rules/starter' => $change]));

    return [
        'errors' => array_map(static fn (array $issue): string => "{$issue['pointer']}: {$issue['message']}", $result->errors()),
        'warnings' => array_map(static fn (array $issue): string => "{$issue['pointer']}: {$issue['message']}", $result->warnings()),
    ];
}

describe('the lap on the thread', function () {
    it('gives the reference car exactly the base lap of the track, in every weather its climate has, and the rewards of the track', function () {
        $config = frozenOnThread(rulesWithThread(), 'rdzak-gt-4');

        expect($config['lap_ms']['dry'])->toBe(75_000)
            ->and($config['lap_ms']['cloudy'])->toBe(75_000)
            ->and($config['lap_cash_cents']['dry'])->toBe(1_550)
            ->and($config['lap_xp']['dry'])->toBe(20)
            ->and($config['fuel_per_lap_ml'])->toBe(1_500);
    });

    it('does not move the lap of the reference car when the constants of the model move', function () {
        $rules = rulesWithThread();
        $rules['dynamics'] = ['power_ppm' => 900_000, 'lateral_g_ppm' => 1_200_000, 'brake_g_ppm' => 1_300_000, 'launch_g_ppm' => 1_100_000, 'agility' => ['min_ppm' => 300_000, 'max_ppm' => 600_000, 'radius_m' => 120], 'top_speed_ppm' => ['asphalt_dry' => 1_000_000, 'asphalt_wet' => 1_000_000, 'gravel_dry' => 800_000, 'mud' => 500_000, 'snow' => 600_000], 'rough' => ['speed_kmh' => 80, 'exponent_ppm' => 600_000]];

        expect(frozenOnThread($rules, 'rdzak-gt-4')['lap_ms']['dry'])->toBe(75_000)
            ->and(frozenOnThread($rules, 'orzel-v12')['lap_ms']['dry'])->not->toBe(frozenOnThread(rulesWithThread(), 'orzel-v12')['lap_ms']['dry']);
    });

    it('laps the other cars by what the model says: the numbers of the proposal on the street', function () {
        $rules = rulesWithThread();

        expect(frozenOnThread($rules, 'bizon-m-2')['lap_ms']['dry'])->toBeBetween(73_005, 73_007)
            ->and(frozenOnThread($rules, 'orzel-v12')['lap_ms']['dry'])->toBeBetween(67_831, 67_833)
            ->and(frozenOnThread($rules, 'rdzak-gt-4')['lap_ms']['rain'])->toBeBetween(78_885, 78_888);
    });

    it('is slower in the rain than in the dry, and slower at night on a track that has no lights, as it was', function () {
        $config = frozenOnThread(rulesWithThread(), 'rdzak-gt-4');
        $unlit = frozenOnThread(rulesWithThread(track: ['lit' => false]), 'rdzak-gt-4');

        expect($config['lap_ms']['rain'])->toBeGreaterThan($config['lap_ms']['dry'])
            ->and($config['lap_ms']['downpour'])->toBeGreaterThan($config['lap_ms']['rain'])
            ->and($config['night_lap_ppm'])->toBe(1_000_000)
            ->and($unlit['night_lap_ppm'])->toBeGreaterThan(1_000_000);
    });

    it('is driven by the formula where the track does not say it is driven on its thread, thread or not', function () {
        $formula = frozenOnThread(threadRules(), 'bizon-m-2');
        $drawn = rulesWithThread(track: ['lap_from' => 'stats']);
        $unsaid = rulesWithThread();
        unset($unsaid['tracks']['zlomowo-street']['lap_from']);

        expect(frozenOnThread($drawn, 'bizon-m-2')['lap_ms'])->toBe($formula['lap_ms'])
            ->and(frozenOnThread($unsaid, 'bizon-m-2')['lap_ms'])->toBe($formula['lap_ms'])
            ->and(Rules::fromArray($drawn)->lapFrom('zlomowo-street'))->toBe('stats')
            ->and(Rules::fromArray(threadRules())->lapFrom('zlomowo-street'))->toBe('stats');
    });

    it('wears the parts by the surfaces the lap is made of: the whole lap on gravel wears them as a gravel track does, and a stretch of it by its share', function () {
        $rally = ['mode' => 'circuit', 'sections' => [
            ['type' => 'straight', 'length_m' => 600], ['type' => 'corner', 'radius_m' => 60, 'angle_deg' => 180, 'turn' => 'right'],
            ['type' => 'straight', 'length_m' => 400, 'surface' => 'gravel'], ['type' => 'corner', 'radius_m' => 60, 'angle_deg' => 180, 'turn' => 'right', 'surface' => 'gravel'],
        ]];
        $rules = Rules::fromArray(rulesWithThread($rally));
        $shares = $rules->surfaceShares('zlomowo-street');
        $odometer = ['mileage_m' => 0, 'serviced_at_m' => 0];
        $factory = fn (array $document): RunInputsFactory => new RunInputsFactory(app(Catalog::class), Rules::fromArray($document));
        $mounted = ['tires' => 'street-tires-t1'];

        $mixed = $factory(rulesWithThread($rally))->wearPlan('rdzak-gt-4', 'zlomowo-street', $mounted, $odometer);
        $asphalt = $factory(threadRules())->wearPlan('rdzak-gt-4', 'zlomowo-street', $mounted, $odometer);

        expect(array_keys($shares))->toBe(['asphalt', 'gravel'])
            ->and(array_sum($shares))->toEqualWithDelta(1.0, 1e-9)
            ->and($shares['gravel'])->toEqualWithDelta((400 + 60 * M_PI) / (600 + 400 + 120 * M_PI), 1e-9)
            ->and($mixed->perKmPpb['tires'])->toBeGreaterThan($asphalt->perKmPpb['tires'])
            ->and($mixed->perKmPpb['tires'])->toBeLessThan($asphalt->perKmPpb['tires'] * 2)
            ->and(Rules::fromArray(threadRules())->surfaceShares('zlomowo-street'))->toBe(['asphalt' => 1.0]);
    });
});

describe('the constants of the model', function () {
    it('are the defaults of the model where the rules say nothing, and the ones the rules say where they do', function () {
        $default = Rules::fromArray(threadRules())->dynamics();
        $changed = threadRules();
        $changed['dynamics'] = ['power_ppm' => 500_000, 'lateral_g_ppm' => 1_000_000, 'brake_g_ppm' => 1_100_000, 'launch_g_ppm' => 900_000, 'agility' => ['min_ppm' => 200_000, 'max_ppm' => 800_000, 'radius_m' => 150], 'top_speed_ppm' => ['asphalt_dry' => 1_000_000, 'asphalt_wet' => 950_000, 'gravel_dry' => 800_000, 'mud' => 500_000, 'snow' => 600_000], 'rough' => ['speed_kmh' => 100, 'exponent_ppm' => 400_000]];
        $read = Rules::fromArray($changed)->dynamics();

        expect($default)->toEqual(new Dynamics)
            ->and($read->power)->toBe(0.5)
            ->and($read->brakeG)->toBe(1.1)
            ->and($read->agilityRadiusM)->toBe(150.0)
            ->and($read->topSpeedOnSurface['asphalt_wet'])->toBe(0.95)
            ->and($read->roughRefKmh)->toBe(100.0)
            ->and($read->roughExponent)->toBe(0.4);
    });
});

describe('the validator', function () {
    it('lets the thread of a track through, whether its lap is driven on it or not', function () {
        $driven = threadValidated(fn (array $rules): array => rulesWithThread(rules: $rules));
        $drawn = threadValidated(fn (array $rules): array => rulesWithThread(track: ['lap_from' => 'stats'], rules: $rules));

        expect($driven['errors'])->toBe([])
            ->and($drawn['errors'])->toBe([]);
    });

    it('says in words what is wrong with a thread', function (Closure $change, string $pointer, string $words) {
        $said = threadValidated($change);
        $found = array_values(array_filter([...$said['errors'], ...$said['warnings']], static fn (string $line): bool => str_contains($line, $pointer)));

        expect($found)->not->toBe([])
            ->and(implode(' ', $found))->toContain($words);
    })->with([
        'sections that do not add up to the length' => [fn (array $rules): array => rulesWithThread(track: ['length_m' => 2_000], rules: $rules), '/tracks/zlomowo-street/thread/sections', 'add up to 2599 m'],
        'a lap on a thread without a thread' => [function (array $rules): array {
            $rules['tracks']['zlomowo-street']['lap_from'] = 'thread';
            unset($rules['tracks']['zlomowo-street']['thread']);

            return $rules;
        }, '/tracks/zlomowo-street/lap_from', 'has none'],
        'a reference car that cannot be driven' => [function (array $rules): array {
            $rules = rulesWithThread(rules: $rules);
            unset($rules['tracks']['zlomowo-street']['reference']['top_speed']);

            return $rules;
        }, '/tracks/zlomowo-street/reference', 'top_speed'],
        'a corner without a radius' => [function (array $rules): array {
            $thread = streetThread();
            unset($thread['sections'][1]['radius_m']);

            return rulesWithThread($thread, rules: $rules);
        }, '/tracks/zlomowo-street/thread/sections/1', 'needs a radius and an angle'],
        'a circuit that is not a loop' => [fn (array $rules): array => rulesWithThread(['mode' => 'circuit', 'sections' => [['type' => 'straight', 'length_m' => 700], ['type' => 'corner', 'radius_m' => 80, 'angle_deg' => 90, 'turn' => 'right'], ['type' => 'straight', 'length_m' => 700], ['type' => 'corner', 'radius_m' => 80, 'angle_deg' => 90, 'turn' => 'right']]], rules: $rules), '/tracks/zlomowo-street/thread/sections', 'turn the car 180° in all'],
        'a circuit without a corner' => [fn (array $rules): array => rulesWithThread(['mode' => 'circuit', 'sections' => [['type' => 'straight', 'length_m' => 2_600]]], rules: $rules), '/tracks/zlomowo-street/thread/sections', 'no corner'],
        'a way back for a circuit' => [fn (array $rules): array => rulesWithThread([...streetThread(), 'return_ms' => 5_000], rules: $rules), '/tracks/zlomowo-street/thread/return_ms', 'no way back'],
        'a radius under the least' => [function (array $rules): array {
            $thread = streetThread();
            $thread['sections'][1]['radius_m'] = 3;

            return rulesWithThread($thread, rules: $rules);
        }, '/tracks/zlomowo-street/thread/sections/1/radius_m', ''],
        'handling that is more of a wide bend than of a hairpin' => [function (array $rules): array {
            $rules['dynamics'] = ['power_ppm' => 600_000, 'lateral_g_ppm' => 950_000, 'brake_g_ppm' => 1_000_000, 'launch_g_ppm' => 950_000, 'agility' => ['min_ppm' => 800_000, 'max_ppm' => 200_000, 'radius_m' => 190], 'top_speed_ppm' => ['asphalt_dry' => 1_000_000, 'asphalt_wet' => 1_000_000, 'gravel_dry' => 850_000, 'mud' => 600_000, 'snow' => 700_000], 'rough' => ['speed_kmh' => 90, 'exponent_ppm' => 500_000]];

            return $rules;
        }, '/dynamics/agility', 'other way round'],
    ]);

    it('has nothing to say of the tracks of the repository', function () {
        $said = threadValidated(fn (array $rules): array => $rules);

        expect(array_filter([...$said['errors'], ...$said['warnings']], static fn (string $line): bool => str_contains($line, 'thread') || str_contains($line, 'dynamics')))->toBe([]);
    });
});

describe('the form of the world', function () {
    it('gives a track that has a thread the length its sections add up to, whatever the box of the length says', function () {
        $rules = rulesWithThread();
        $state = WorldForm::toState($rules);
        $state['tracks'][0]['length'] = 1;

        expect(WorldForm::apply($rules, $state)['tracks']['zlomowo-street']['length_m'])->toBe($rules['tracks']['zlomowo-street']['length_m']);
    });

    it('keeps the thread of a track and where its lap comes from, so that saving the tracks does not lose them', function () {
        $rules = rulesWithThread();
        $made = WorldForm::apply($rules, WorldForm::toState($rules));

        expect($made)->toEqual($rules)
            ->and($made['tracks']['zlomowo-street']['thread'])->toBe(streetThread())
            ->and($made['tracks']['zlomowo-street']['lap_from'])->toBe('thread')
            ->and(array_key_exists('thread', $made['tracks']['suchy-pas-drag']))->toBeTrue()
            ->and(array_key_exists('lap_from', $made['tracks']['suchy-pas-drag']))->toBeFalse();
    });
});
