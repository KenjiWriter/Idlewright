<?php

declare(strict_types=1);

use App\Content\Catalog;
use App\Content\Definitions\WorldForm;
use App\Content\PackValidator;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Content\Tracks\Build;
use App\Content\Tracks\TrackOutcome;
use App\Content\Tracks\TrackReport;
use App\Domain\Run\RunFreezer;

// A rally is a track of the kind `rally` that is driven on a mix of two surfaces (`surface_mix`): the tires of a car grip it by the shares of the mix, the wear of the parts
// is the one of the mix, the validator wants the shares to add up, the form of the world keeps the mix, and the terrain gate does not count a rally as gravel or as asphalt.

/** The rules of the repository with the quarry of Kruszyn driven as a rally on 60% gravel and 40% asphalt, unless the mix is said otherwise. */
function rallyRules(?array $mix = null, string $kind = 'rally'): Rules
{
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
    $data['tracks']['kruszyn-quarry'] = [...$data['tracks']['kruszyn-quarry'], 'kind' => $kind, 'surface_mix' => $mix ?? [['surface' => 'gravel', 'share_ppm' => 600_000], ['surface' => 'asphalt', 'share_ppm' => 400_000]]];

    return Rules::fromArray($data);
}

/** The dry lap of a car on the track with these tires, in ms. */
function rallyLap(Rules $rules, string $tires, string $track = 'kruszyn-quarry'): int
{
    $factory = (new RunInputsFactory(app(Catalog::class), $rules))->withoutBots();

    return (new RunFreezer)->freeze($factory->make('rdzak-gt-4', $track, ['tires' => ['slug' => $tires, 'rarity' => 1]]), 'rally', 0)->config['lap_ms']['dry'];
}

describe('the tires on a rally', function () {
    it('grip it by the shares of its mix: the lap of tires that are good on one surface is between what they do on the two', function () {
        $gravel = Rules::fromArray(json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR));
        $rally = rallyRules();
        // The same track made of asphalt alone, to set the mix against it.
        $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
        $data['tracks']['kruszyn-quarry']['surface'] = 'asphalt';
        $asphalt = Rules::fromArray($data);

        $street = ['gravel' => rallyLap($gravel, 'street-tires-t2'), 'rally' => rallyLap($rally, 'street-tires-t2'), 'asphalt' => rallyLap($asphalt, 'street-tires-t2')];

        // Street tires grip asphalt best and gravel worst, so the mix is slower than the asphalt and quicker than the gravel.
        expect($street['asphalt'])->toBeLessThan($street['rally'])->and($street['rally'])->toBeLessThan($street['gravel']);
        // Off-road tires are the other way round, and a mix is not the same as either: a rally asks for a choice of tires.
        $offroad = ['gravel' => rallyLap($gravel, 'offroad-tires-t2'), 'rally' => rallyLap($rally, 'offroad-tires-t2'), 'asphalt' => rallyLap($asphalt, 'offroad-tires-t2')];

        expect($offroad['gravel'])->toBeLessThan($offroad['rally'])->and($offroad['rally'])->toBeLessThan($offroad['asphalt']);
    });

    it('make a track of one surface what it was: a mix of one surface, or none, gives the lap that the surface gives', function () {
        $none = Rules::fromArray(json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR));
        $one = rallyRules([['surface' => 'gravel', 'share_ppm' => 1_000_000]], 'standard');

        expect(rallyLap($one, 'offroad-tires-t2'))->toBe(rallyLap($none, 'offroad-tires-t2'));
    });

    it('wear the parts by the mix: the shares of the lap are the ones of the mix', function () {
        $shares = rallyRules()->surfaceShares('kruszyn-quarry');

        expect($shares)->toEqualWithDelta(['gravel' => 0.6, 'asphalt' => 0.4], 0.000001);
    });
});

describe('the mix of surfaces in the rules', function () {
    it('is accepted when the shares add up and a rally has one, and refused when they do not, when a surface is there twice, or when a rally has none', function () {
        $with = fn (callable $change) => PackValidator::fromConfig()->validateSource(contentWith(['rules/starter' => function (array $rules) use ($change): array {
            $change($rules['tracks']['kruszyn-quarry']);

            return $rules;
        }]));
        $mix = fn (array &$track, array $parts) => $track = [...$track, 'kind' => 'rally', 'surface_mix' => $parts];

        $ok = $with(fn (array &$track) => $mix($track, [['surface' => 'gravel', 'share_ppm' => 600_000], ['surface' => 'asphalt', 'share_ppm' => 400_000]]));
        $short = $with(fn (array &$track) => $mix($track, [['surface' => 'gravel', 'share_ppm' => 600_000], ['surface' => 'asphalt', 'share_ppm' => 300_000]]));
        $twice = $with(fn (array &$track) => $mix($track, [['surface' => 'gravel', 'share_ppm' => 500_000], ['surface' => 'gravel', 'share_ppm' => 500_000]]));
        $bare = $with(function (array &$track) {
            $track['kind'] = 'rally';
        });

        expect(array_column($ok->errors(), 'pointer'))->not->toContain('/tracks/kruszyn-quarry/surface_mix')
            ->and(array_column($short->errors(), 'pointer'))->toContain('/tracks/kruszyn-quarry/surface_mix')
            ->and(array_column($twice->errors(), 'pointer'))->toContain('/tracks/kruszyn-quarry/surface_mix')
            ->and(array_column($bare->errors(), 'pointer'))->toContain('/tracks/kruszyn-quarry/surface_mix');
    });

    it('is kept by the form of the world, in percent, and comes back as the same document', function () {
        $rules = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
        $rules['tracks']['kruszyn-quarry']['kind'] = 'rally';
        $rules['tracks']['kruszyn-quarry']['surface_mix'] = [['surface' => 'gravel', 'share_ppm' => 650_000], ['surface' => 'asphalt', 'share_ppm' => 350_000]];

        $state = WorldForm::toState($rules);
        $line = collect($state['tracks'])->firstWhere('slug', 'kruszyn-quarry');

        expect($line['surface_mix'])->toBe([['surface' => 'gravel', 'percent' => 65], ['surface' => 'asphalt', 'percent' => 35]])
            ->and(WorldForm::apply($rules, $state)['tracks']['kruszyn-quarry']['surface_mix'])->toBe($rules['tracks']['kruszyn-quarry']['surface_mix']);
    });
});

describe('the gate of the terrain', function () {
    /** The outcome of a track that a car wins. */
    function rallyOutcome(string $track, string $surface, string $car, string $kind): TrackOutcome
    {
        return new TrackOutcome($track, $surface, 1, 1, 1, new Build($car), 1.0, ['dry' => 1000], [$car => 1.0], [], $kind);
    }

    it('leaves the rallies out: a rally is driven on both surfaces, so the car that wins it does not make the car that wins on asphalt a car that wins on gravel too', function () {
        $report = new TrackReport('v', [], [
            rallyOutcome('a', 'asphalt', 'rdzak-gt-4', 'standard'),
            rallyOutcome('g', 'gravel', 'bizon-m-2', 'standard'),
            // The Rdzak also wins a rally whose base surface is gravel: that is not a gravel track.
            rallyOutcome('r', 'gravel', 'rdzak-gt-4', 'rally'),
        ]);

        expect($report->terrainPassed())->toBeTrue();

        $without = new TrackReport('v', [], [rallyOutcome('a', 'asphalt', 'rdzak-gt-4', 'standard'), rallyOutcome('g', 'gravel', 'rdzak-gt-4', 'standard')]);

        expect($without->terrainPassed())->toBeFalse();
    });

    it('counts the rallies among themselves: no car wins more than two of them', function () {
        $wins = fn (array $cars): TrackReport => new TrackReport('v', [], array_map(fn (string $car, int $index): TrackOutcome => rallyOutcome("r{$index}", 'gravel', $car, 'rally'), $cars, array_keys($cars)));

        expect($wins(['rdzak-gt-4', 'rdzak-gt-4', 'bizon-m-2', 'bizon-m-2', 'zlomo-x'])->m3Passed())->toBeTrue()
            ->and($wins(['rdzak-gt-4', 'rdzak-gt-4', 'rdzak-gt-4', 'bizon-m-2', 'zlomo-x'])->carsThatWinTooMuch())->toHaveKey('rdzak-gt-4 (rally)');
    });
});
