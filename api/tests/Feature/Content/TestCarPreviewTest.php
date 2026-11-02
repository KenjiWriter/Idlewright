<?php

declare(strict_types=1);

use App\Content\Definitions\JsonDocument;
use App\Content\Publishing\TestCarPreview;
use App\Content\RunInputsFactory;
use App\Domain\Run\RunFreezer;
use App\Models\ContentDefinition;
use App\Services\ContentDrafts;
use Illuminate\Support\Facades\Artisan;

// What test cars do on the tracks of the game on the live content and on the draft: the service that the panel shows next to a form, and its command.

const TEST_CAR = 'rdzak-gt-4';

const TEST_TIRES = ['tires' => ['slug' => 'street-tires-t2', 'rarity' => 3, 'upgrade' => 2]];

function previewCars(array $cars, ?array $tracks = null, array $replace = []): array
{
    return app(TestCarPreview::class)->preview($cars, $tracks, $replace);
}

/** A part of the repository saved as a draft with other statistics. @param  array<string, int|float>  $stats */
function changeDraftPart(string $slug, array $stats): void
{
    $document = JsonDocument::decode((string) repositoryContent()->raw("parts/{$slug}"));
    $document['stats'] = [...$document['stats'], ...$stats];

    app(ContentDrafts::class)->save(ContentDefinition::query()->where('kind', 'part')->where('slug', $slug)->firstOrFail(), $document, anAdmin());
}

describe('a test car on the live content', function () {
    it('has its lap, its place among the bots and what an hour pays, from the run that the game freezes for the car on the track', function () {
        $car = previewCars([['car' => TEST_CAR]], ['zlomowo-street'])['live']['cars'][0]['tracks']['zlomowo-street'];
        $config = (new RunFreezer)->freeze(app(RunInputsFactory::class)->make(TEST_CAR, 'zlomowo-street'), 'check', 0)->config;

        expect($car['lap_ms']['dry'])->toBe($config['lap_ms']['dry'])
            ->and($car['reward_cents_per_lap'])->toBe($config['lap_cash_cents']['dry'])
            ->and($car['xp_per_lap'])->toBe($config['lap_xp']['dry'])
            ->and($car['fuel_per_lap_ml'])->toBe($config['fuel_per_lap_ml'])
            ->and($car['position'])->toBe($config['field']['position'])
            ->and($car['position'])->toBeBetween(1, $car['field_size'])
            ->and($car['laps_per_hour'])->toEqualWithDelta(3_600_000 / $car['lap_ms']['dry'], 0.01)
            ->and($car['profit_cents_per_hour'])->toBe($car['income_cents_per_hour'] - $car['fuel_cents_per_hour'])
            ->and($car['xp_per_hour'])->toBe((int) round($car['xp_per_lap'] * 3_600_000 / $car['lap_ms']['dry']));
    });

    it('is played on every track of the game when no track is named, and on the ones that are named', function () {
        $all = previewCars([['car' => TEST_CAR]])['live']['cars'][0]['tracks'];
        $some = previewCars([['car' => TEST_CAR]], ['zlomowo-street'])['live']['cars'][0]['tracks'];

        expect(array_keys($all))->toBe(array_keys(repositoryDocument('rules/starter')['tracks']))
            ->and(array_keys($some))->toBe(['zlomowo-street'])
            ->and(previewCars([['car' => TEST_CAR]], ['no-such-track'])['live']['cars'][0]['tracks']['no-such-track'])->toBe(['error' => 'unknown_track']);
    });

    it('is faster with parts of a higher rarity and a higher upgrade on it, the way the garage mounts them', function () {
        $lap = fn (array $parts): int => previewCars([['car' => TEST_CAR, 'level' => 10, 'parts' => $parts]], ['zlomowo-street'])['live']['cars'][0]['tracks']['zlomowo-street']['lap_ms']['dry'];

        $stock = $lap([]);
        $common = $lap(['tires' => 'street-tires-t2']);
        $rare = $lap(['tires' => ['slug' => 'street-tires-t2', 'rarity' => 3]]);
        $upgraded = $lap(TEST_TIRES);

        expect($common)->toBeLessThan($stock)->and($rare)->toBeLessThan($common)->and($upgraded)->toBeLessThan($rare);
    });

    it('gives the statistics of the car with the parts on it, and says what the garage would refuse about them while it plays them all the same', function () {
        $car = previewCars([['car' => TEST_CAR, 'level' => 1, 'parts' => ['tires' => ['slug' => 'street-tires-t2', 'rarity' => 1, 'upgrade' => 9]]]], ['zlomowo-street'])['live']['cars'][0];
        $codes = array_column($car['issues'], 'code');

        expect($car['stats'])->toHaveKey('grip')
            ->and($codes)->toContain('upgrade_above_cap', 'car_level_required')
            ->and(collect($car['issues'])->firstWhere('code', 'upgrade_above_cap')['cap'])->toBeLessThan(9)
            // The preview says what the car would do, not whether the garage allows it.
            ->and($car['tracks']['zlomowo-street'])->toHaveKey('lap_ms');
    });

    it('cannot play a car whose part does not exist or is for another slot, and says which part it is', function () {
        $wrongSlot = previewCars([['car' => TEST_CAR, 'level' => 10, 'parts' => ['brakes' => 'street-tires-t2']]], ['zlomowo-street'])['live']['cars'][0];
        $unknown = previewCars([['car' => TEST_CAR, 'level' => 10, 'parts' => ['exhaust' => 'no-such-part']]], ['zlomowo-street'])['live']['cars'][0];

        expect(array_column($wrongSlot['issues'], 'code'))->toContain('wrong_slot', 'cannot_race')
            ->and($wrongSlot['stats'])->toBeNull()
            ->and($wrongSlot['tracks'])->toBe([])
            ->and(collect($wrongSlot['issues'])->firstWhere('code', 'wrong_slot')['part_slot'])->toBe('tires')
            ->and(array_column($unknown['issues'], 'code'))->toContain('unknown_part', 'cannot_race')
            ->and($unknown['tracks'])->toBe([]);
    });

    it('says that a part needs a car of a higher level than the test car has', function () {
        $issues = previewCars([['car' => TEST_CAR, 'level' => 1, 'parts' => ['tires' => 'street-tires-t2']]], ['zlomowo-street'])['live']['cars'][0]['issues'];
        $found = collect($issues)->firstWhere('code', 'car_level_required');

        // The tires of the second tier need a car of more than the first level, and the same car at a higher level has no such trouble.
        expect($found)->not->toBeNull()
            ->and($found['current'])->toBe(1)
            ->and($found['required'])->toBeGreaterThan(1)
            ->and(previewCars([['car' => TEST_CAR, 'level' => 30, 'parts' => ['tires' => 'street-tires-t2']]], ['zlomowo-street'])['live']['cars'][0]['issues'])->toBe([]);
    });

    it('says that there is no such car, and plays the others', function () {
        $answer = previewCars([['car' => 'no-such-car'], ['car' => TEST_CAR]], ['zlomowo-street']);

        expect($answer['live']['cars'][0])->toBe(['issues' => [['code' => 'unknown_car', 'car' => 'no-such-car']], 'stats' => null, 'tracks' => []])
            ->and($answer['live']['cars'][1]['tracks'])->toHaveKey('zlomowo-street');
    });

    it('refuses test cars that are not made as it asks', function () {
        expect(fn () => previewCars([]))->toThrow(InvalidArgumentException::class, 'between 1 and 8')
            ->and(fn () => previewCars(array_fill(0, 9, ['car' => TEST_CAR])))->toThrow(InvalidArgumentException::class)
            ->and(fn () => previewCars([['level' => 3]]))->toThrow(InvalidArgumentException::class, 'has no car')
            ->and(fn () => previewCars([['car' => TEST_CAR, 'parts' => [['slug' => 'x']]]]))->toThrow(InvalidArgumentException::class, 'no slot');
    });

    it('keeps the label and the level of a car and holds the rarity and the upgrade to what the game has', function () {
        $car = previewCars([['label' => 'Rare tires', 'car' => TEST_CAR, 'level' => 0, 'parts' => ['tires' => ['slug' => 'street-tires-t2', 'rarity' => 9, 'upgrade' => -3]]]])['cars'][0];

        expect($car)->toBe(['label' => 'Rare tires', 'car' => TEST_CAR, 'level' => 1, 'parts' => ['tires' => ['slug' => 'street-tires-t2', 'rarity' => 5, 'upgrade' => 0]]]);
    });
});

describe('the draft', function () {
    it('is not there when the game has no content in the database to work on', function () {
        $answer = previewCars([['car' => TEST_CAR]], ['zlomowo-street']);

        expect($answer['draft'])->toBeNull()->and($answer['diff'])->toBe([])->and($answer['live']['source']['kind'])->toBe('live');
    });

    it('is the live content when nothing was changed, and every difference is nought', function () {
        contentPacks()->import(repositoryContent(), activate: true, checkTables: false);

        $answer = previewCars([['car' => TEST_CAR, 'level' => 10, 'parts' => TEST_TIRES]], ['zlomowo-street']);
        $change = $answer['diff'][0]['zlomowo-street'];

        expect($answer['draft']['source']['kind'])->toBe('draft')
            ->and($answer['draft']['cars'][0]['tracks']['zlomowo-street']['lap_ms'])->toBe($answer['live']['cars'][0]['tracks']['zlomowo-street']['lap_ms'])
            ->and(array_column($change, 'delta'))->toBe([0, 0, 0, 0]);
    });

    it('shows what a changed part does to the same car, next to the live one, which is left as it was', function () {
        contentPacks()->import(repositoryContent(), activate: true, checkTables: false);
        changeDraftPart('street-tires-t2', ['grip' => 140, 'handling' => 138, 'braking' => 140]);

        $answer = previewCars([['car' => TEST_CAR, 'level' => 10, 'parts' => TEST_TIRES]], ['zlomowo-street']);
        $live = $answer['live']['cars'][0]['tracks']['zlomowo-street'];
        $draft = $answer['draft']['cars'][0]['tracks']['zlomowo-street'];
        $change = $answer['diff'][0]['zlomowo-street'];

        expect($draft['lap_ms']['dry'])->toBeLessThan($live['lap_ms']['dry'])
            ->and($draft['profit_cents_per_hour'])->toBeGreaterThan($live['profit_cents_per_hour'])
            ->and($change['lap_ms']['delta'])->toBe($draft['lap_ms']['dry'] - $live['lap_ms']['dry'])
            ->and($change['lap_ms']['delta'])->toBeLessThan(0)
            ->and($change['profit_cents_per_hour']['delta'])->toBeGreaterThan(0)
            ->and($answer['draft']['cars'][0]['stats']['grip'])->toBeGreaterThan($answer['live']['cars'][0]['stats']['grip'])
            ->and(previewCars([['car' => TEST_CAR, 'level' => 10, 'parts' => TEST_TIRES]], ['zlomowo-street'])['live']['cars'][0]['tracks']['zlomowo-street']['lap_ms'])->toBe($live['lap_ms']);
    });

    it('takes a document that is not saved yet over the draft, so that a form can be tried before it is saved', function () {
        contentPacks()->import(repositoryContent(), activate: true, checkTables: false);

        $document = JsonDocument::decode((string) repositoryContent()->raw('parts/street-tires-t2'));
        $document['stats'] = [...$document['stats'], 'grip' => 150, 'handling' => 150, 'braking' => 150];
        $text = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $answer = previewCars([['car' => TEST_CAR, 'level' => 10, 'parts' => TEST_TIRES]], ['zlomowo-street'], ['parts/street-tires-t2' => $text]);

        expect($answer['diff'][0]['zlomowo-street']['lap_ms']['delta'])->toBeLessThan(0)
            ->and(ContentDefinition::query()->where('slug', 'street-tires-t2')->firstOrFail()->draft)->toBeNull();
    });

    it('plays a part that only the draft has: the live side says it does not know it', function () {
        contentPacks()->import(repositoryContent(), activate: true, checkTables: false);

        $document = [...JsonDocument::decode((string) repositoryContent()->raw('parts/street-tires-t2')), 'slug' => 'street-tires-t9'];
        $document['stats'] = [...$document['stats'], 'grip' => 150];
        app(ContentDrafts::class)->create('part', $document, anAdmin());

        $answer = previewCars([['car' => TEST_CAR, 'level' => 10, 'parts' => ['tires' => 'street-tires-t9']]], ['zlomowo-street']);

        $stock = previewCars([['car' => TEST_CAR, 'level' => 10]], ['zlomowo-street'])['live']['cars'][0]['stats']['grip'];

        expect(array_column($answer['live']['cars'][0]['issues'], 'code'))->toContain('unknown_part')
            ->and($answer['live']['cars'][0]['tracks'])->toBe([])
            ->and($answer['draft']['cars'][0]['issues'])->toBe([])
            ->and($answer['draft']['cars'][0]['stats']['grip'])->toBeGreaterThan($stock)
            ->and($answer['diff'][0])->toBe([]);
    });
});

describe('economy:preview', function () {
    it('prints the test car on the live content and the draft, track by track', function () {
        contentPacks()->import(repositoryContent(), activate: true, checkTables: false);

        Artisan::call('economy:preview', ['--cars' => json_encode([['label' => 'Test', 'car' => TEST_CAR, 'level' => 10, 'parts' => TEST_TIRES]]), '--track' => ['zlomowo-street']]);
        $output = Artisan::output();

        expect($output)->toContain('Live content')
            ->and($output)->toContain('Test (rdzak-gt-4, level 10, tires: street-tires-t2 r3 +2)')
            ->and($output)->toContain('zlomowo-street')
            ->and($output)->toContain('profit an hour');
    });

    it('prints the answer of the service as JSON, for the panel or another program to read', function () {
        Artisan::call('economy:preview', ['--json' => true, '--cars' => json_encode([['car' => TEST_CAR]]), '--track' => ['zlomowo-street']]);
        $answer = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        expect(array_keys($answer))->toBe(['cars', 'live', 'draft', 'diff'])
            ->and($answer['live']['cars'][0]['tracks']['zlomowo-street'])->toHaveKeys(['lap_ms', 'position', 'income_cents_per_hour', 'fuel_cents_per_hour', 'profit_cents_per_hour', 'xp_per_hour']);
    });

    it('plays the car of the part preview when it is not given one, and refuses cars it cannot read', function () {
        $this->artisan('economy:preview', ['--track' => ['zlomowo-street']])->expectsOutputToContain('rdzak-gt-4')->assertExitCode(0);
        $this->artisan('economy:preview', ['--cars' => 'not json'])->assertExitCode(2);
        $this->artisan('economy:preview', ['--file' => 'no/such/file.json'])->expectsOutputToContain('There is no file')->assertExitCode(2);
        $this->artisan('economy:preview', ['--cars' => '[]'])->expectsOutputToContain('between 1 and 8')->assertExitCode(2);
    });
});
