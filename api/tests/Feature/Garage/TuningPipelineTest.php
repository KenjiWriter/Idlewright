<?php

declare(strict_types=1);

use App\Content\Catalog;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Content\ThreadDocument;
use App\Domain\Run\RunFreezer;

/** A bonus that a place holds. */
function tuned(string $key, int $ppm): array
{
    return ['key' => $key, 'ppm' => $ppm, 'tries' => 1, 'fails' => 0];
}

/**
 * The parts of a car of the shop, each with the bonuses given for its slot.
 *
 * @param  array<string, list<array{key: string, ppm: int, tries: int, fails: int}|null>>  $bonuses  by slot
 * @return array<string, array<string, mixed>>
 */
function tunedCar(array $bonuses = [], string $tires = 'street-tires-t2'): array
{
    $mounted = [
        'tires' => ['slug' => $tires, 'rarity' => 1],
        'suspension' => ['slug' => 'sport-suspension-t1', 'rarity' => 1],
        'brakes' => ['slug' => 'disc-brakes-t2', 'rarity' => 1],
        'exhaust' => ['slug' => 'sport-exhaust-t2', 'rarity' => 1],
        'gearbox' => ['slug' => 'manual-5-gearbox-t2', 'rarity' => 1],
    ];

    foreach ($bonuses as $slot => $places) {
        $mounted[$slot]['tuning'] = $places;
    }

    return $mounted;
}

function tuningFactory(?Rules $rules = null): RunInputsFactory
{
    return $rules === null ? app(RunInputsFactory::class)->withoutBots() : (new RunInputsFactory(app(Catalog::class), $rules))->withoutBots();
}

/** The lap of a car frozen on a track, by weather, in ms. */
function tuningLaps(RunInputsFactory $factory, string $track, array $mounted): array
{
    return (new RunFreezer)->freeze($factory->make('rdzak-gt-4', $track, $mounted), 'tuning-pipeline', 0)->config['lap_ms'];
}

/** The rules of the repository with the street driven on a thread of fourteen sections, its base lap said by hand. */
function tuningThreadRules(): Rules
{
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
    $straight = static fn (int $length): array => ['type' => 'straight', 'length_m' => $length];
    $corner = static fn (int $radius, int $angle, string $turn = 'right'): array => ['type' => 'corner', 'radius_m' => $radius, 'angle_deg' => $angle, 'turn' => $turn];
    $thread = ['mode' => 'circuit', 'sections' => [
        $straight(629), $corner(70, 90), $straight(295), $corner(40, 90), $straight(206), $corner(90, 90), $straight(423), $corner(28, 180), $straight(256),
        $corner(80, 45, 'left'), $straight(29), $corner(80, 45), $straight(147), $corner(55, 90, 'left'),
    ]];
    $data['tracks']['zlomowo-street'] = [...$data['tracks']['zlomowo-street'], 'lap_from' => 'thread', 'thread' => $thread, 'length_m' => ThreadDocument::lengthM($thread), 'base_lap_ms' => 75_000];

    return Rules::fromArray($data);
}

describe('the bonuses of the tuning in the pipeline', function () {
    it('change nothing when the places are empty, the part was never tuned, or the pack does not know the bonus', function () {
        $factory = tuningFactory();
        $plain = tuningLaps($factory, 'zlomowo-street', tunedCar());

        expect(tuningLaps($factory, 'zlomowo-street', tunedCar(['tires' => [null, null, null]])))->toBe($plain)
            ->and(tuningLaps($factory, 'zlomowo-street', tunedCar(['tires' => []])))->toBe($plain)
            ->and(tuningLaps($factory, 'zlomowo-street', tunedCar(['tires' => [tuned('vanished', 120_000)]])))->toBe($plain);
    });

    it('about a statistic work on tracks of their kind and on no other, and a car that asks for no track gets only the ones that work everywhere', function () {
        $factory = tuningFactory();
        $bonuses = ['gearbox' => [tuned('sprint-launch', 120_000)]];
        $without = $factory->stats('rdzak-gt-4', tunedCar(), 0, 'sprint');
        $with = $factory->stats('rdzak-gt-4', tunedCar($bonuses), 0, 'sprint');

        // 12% more acceleration on a sprint track, and nothing else changes.
        expect($with->stats['accel'] / $without->stats['accel'])->toEqualWithDelta(1.12, 0.000001)
            ->and($with->stats['braking'])->toEqual($without->stats['braking'])
            ->and($factory->stats('rdzak-gt-4', tunedCar($bonuses), 0, 'endurance')->stats)->toEqual($factory->stats('rdzak-gt-4', tunedCar(), 0, 'endurance')->stats)
            // The rating of a car does not depend on a track: with no kind the sprint bonus is not counted.
            ->and($factory->stats('rdzak-gt-4', tunedCar($bonuses))->stats)->toEqual($factory->stats('rdzak-gt-4', tunedCar())->stats);

        $sprint = tuningLaps($factory, 'zlomowo-yard-dash', tunedCar($bonuses));

        expect($sprint['dry'])->toBeLessThan(tuningLaps($factory, 'zlomowo-yard-dash', tunedCar())['dry'])
            ->and(tuningLaps($factory, 'zlomowo-street', tunedCar($bonuses)))->toBe(tuningLaps($factory, 'zlomowo-street', tunedCar()))
            ->and(tuningLaps($factory, 'zlomowo-ring-road', tunedCar($bonuses)))->toBe(tuningLaps($factory, 'zlomowo-ring-road', tunedCar()));
    });

    it('of a surface make the tires grip more on that surface, and on no other', function () {
        $factory = tuningFactory();

        // Dry grip on dry asphalt: the dry lap is shorter, and the lap in the rain (wet asphalt) is not.
        $dry = tuningLaps($factory, 'zlomowo-street', tunedCar(['tires' => [tuned('dry-grip', 80_000)]]));
        $base = tuningLaps($factory, 'zlomowo-street', tunedCar());

        expect($dry['dry'])->toBeLessThan($base['dry'])->and($dry['rain'])->toBe($base['rain'])
            // Gravel grip on gravel (offroad tires on a quarry), and not on asphalt.
            ->and(tuningLaps($factory, 'kruszyn-quarry', tunedCar(['tires' => [tuned('gravel-grip', 80_000)]], 'offroad-tires-t2'))['dry'])->toBeLessThan(tuningLaps($factory, 'kruszyn-quarry', tunedCar([], 'offroad-tires-t2'))['dry'])
            ->and(tuningLaps($factory, 'zlomowo-street', tunedCar(['tires' => [tuned('gravel-grip', 80_000)]])))->toBe($base)
            // Dry grip does nothing on gravel.
            ->and(tuningLaps($factory, 'kruszyn-quarry', tunedCar(['tires' => [tuned('dry-grip', 80_000)]], 'offroad-tires-t2')))->toBe(tuningLaps($factory, 'kruszyn-quarry', tunedCar([], 'offroad-tires-t2')));
    });

    it('of a surface and of a weather work on the sections of a track that is driven on its thread, where the surface is that', function () {
        $factory = tuningFactory(tuningThreadRules());
        $base = tuningLaps($factory, 'zlomowo-street', tunedCar());
        $dry = tuningLaps($factory, 'zlomowo-street', tunedCar(['tires' => [tuned('dry-grip', 80_000)]]));
        $rain = tuningLaps($factory, 'zlomowo-street', tunedCar(['tires' => [tuned('rain-handling', 100_000)]]));

        expect($dry['dry'])->toBeLessThan($base['dry'])->and($dry['rain'])->toBe($base['rain'])
            ->and($rain['rain'])->toBeLessThan($base['rain'])->and($rain['dry'])->toBe($base['dry']);
    });

    it('of a weather multiply the statistic in that weather and in no other', function () {
        $factory = tuningFactory();
        $base = tuningLaps($factory, 'zlomowo-street', tunedCar());
        $rain = tuningLaps($factory, 'zlomowo-street', tunedCar(['tires' => [tuned('rain-handling', 100_000)]]));
        $fog = tuningLaps($factory, 'zlomowo-street', tunedCar(['exhaust' => [tuned('fog-speed', 100_000)]]));

        expect($rain['rain'])->toBeLessThan($base['rain'])->and($rain['downpour'])->toBeLessThan($base['downpour'])
            ->and($rain['dry'])->toBe($base['dry'])->and($rain['fog'])->toBe($base['fog'])
            ->and($fog['fog'])->toBeLessThan($base['fog'])->and($fog['dry'])->toBe($base['dry'])->and($fog['rain'])->toBe($base['rain']);
    });

    it('of a stat on a ground work where the ground is that, whatever the weather makes of it', function () {
        $factory = tuningFactory();
        $bonus = ['suspension' => [tuned('gravel-clearance', 100_000)]];

        expect(tuningLaps($factory, 'kruszyn-quarry', tunedCar($bonus, 'offroad-tires-t2'))['dry'])->toBeLessThan(tuningLaps($factory, 'kruszyn-quarry', tunedCar([], 'offroad-tires-t2'))['dry'])
            ->and(tuningLaps($factory, 'zlomowo-street', tunedCar($bonus)))->toBe(tuningLaps($factory, 'zlomowo-street', tunedCar()));
    });

    it('about the fuel and the reliability work on the tracks of their kind, and the wear too', function () {
        $factory = tuningFactory();
        $fuel = tunedCar(['exhaust' => [tuned('endurance-economy', 100_000)]]);
        $reliable = tunedCar(['brakes' => [tuned('endurance-reliability', 100_000)]]);
        $odometer = ['mileage_m' => 0, 'serviced_at_m' => 0];

        $burn = fn (array $mounted, string $track): float => $factory->make('rdzak-gt-4', $track, $mounted)->car->fuelUseMultiplier;
        $chance = fn (array $mounted, string $track): int => $factory->make('rdzak-gt-4', $track, $mounted)->breakChancePerLapPpm;
        $wear = fn (array $mounted, string $track): int => $factory->wearPlan('rdzak-gt-4', $track, $mounted, $odometer)->perKmPpb['tires'];

        expect($burn($fuel, 'zlomowo-ring-road'))->toEqualWithDelta($burn(tunedCar(), 'zlomowo-ring-road') * 0.9, 0.000001)
            ->and($burn($fuel, 'zlomowo-street'))->toBe($burn(tunedCar(), 'zlomowo-street'))
            ->and($chance($reliable, 'zlomowo-ring-road'))->toBeLessThan($chance(tunedCar(), 'zlomowo-ring-road'))
            ->and($chance($reliable, 'zlomowo-street'))->toBe($chance(tunedCar(), 'zlomowo-street'))
            ->and($wear(tunedCar(['tires' => [tuned('endurance-wear', 100_000)]]), 'zlomowo-ring-road'))->toBe((int) round($wear(tunedCar(), 'zlomowo-ring-road') * 0.9))
            ->and($wear(tunedCar(['tires' => [tuned('endurance-wear', 100_000)]]), 'zlomowo-street'))->toBe($wear(tunedCar(), 'zlomowo-street'));
    });

    it('add no more to one statistic of a car than the rules let it have, all the parts together', function () {
        $capped = Rules::fromSource(contentWith(['rules/starter' => function (array $rules): array {
            $rules['tuning']['car_cap_ppm'] = 100_000;

            return $rules;
        }]));
        $factory = tuningFactory($capped);
        $bonuses = ['brakes' => [tuned('sprint-brake', 120_000)], 'tires' => [tuned('sprint-brake', 120_000)]];

        $base = $factory->stats('rdzak-gt-4', tunedCar(), 0, 'sprint')->stats['braking'];
        $with = $factory->stats('rdzak-gt-4', tunedCar($bonuses), 0, 'sprint')->stats['braking'];

        // 24% would be asked for; the car has 10%.
        expect($with / $base)->toEqualWithDelta(1.10, 0.000001);
    });

    it('give less when the part that holds them is worn', function () {
        $factory = tuningFactory();
        $car = fn (int $wear): array => [...tunedCar(['gearbox' => [tuned('sprint-launch', 100_000)]]), 'gearbox' => ['slug' => 'manual-5-gearbox-t2', 'rarity' => 1, 'wear' => $wear, 'tuning' => [tuned('sprint-launch', 100_000)]]];

        $new = $factory->stats('rdzak-gt-4', $car(0), 0, 'sprint')->stats['accel'];
        $worn = $factory->stats('rdzak-gt-4', $car(700_000_000), 0, 'sprint')->stats['accel'];
        $without = $factory->stats('rdzak-gt-4', tunedCar(), 0, 'sprint')->stats['accel'];

        expect($new / $without)->toEqualWithDelta(1.10, 0.000001)->and($worn / $without)->toBeLessThan(1.10)->and($worn / $without)->toBeGreaterThan(1.0);
    });
});
