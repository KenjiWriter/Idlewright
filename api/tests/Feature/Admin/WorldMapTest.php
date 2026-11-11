<?php

declare(strict_types=1);

use App\Content\Definitions\JsonDocument;
use App\Content\Definitions\WorldForm;
use App\Content\Units\Measures;
use App\Filament\Pages\RulesJsonPage;
use App\Filament\Pages\WorldPage;
use App\Filament\Support\WorldBoard;
use App\Models\ContentDefinition;
use Livewire\Livewire;

// The map of the world on the world page: it draws the cities and the roads as the game does, a city is moved by dragging it, two cities are joined by a road with
// the kilometres the scale of the map gives it, and a road is taken away. There is no city in the middle: any city can be joined to any other.

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false);
    app()->setLocale('pl');
});

/** The state of the world page with the cities at these places, the roads and the tracks given. @return array<string, mixed> */
function boardState(array $places, array $roads = [], array $tracks = []): array
{
    $regions = [];

    foreach ($places as $slug => [$x, $y]) {
        $regions["key-{$slug}"] = ['slug' => $slug, 'name' => ['pl' => ucfirst($slug), 'en' => ucfirst($slug)], 'map' => ['x' => $x, 'y' => $y]];
    }

    $lines = [];

    foreach ($roads as $index => [$from, $to, $km]) {
        $lines["road-{$index}"] = ['from' => $from, 'to' => $to, 'km' => $km];
    }

    return ['regions' => $regions, 'roads' => $lines, 'tracks' => $tracks];
}

/** The rules of the world of the repository. @return array<string, mixed> */
function publishedWorld(): array
{
    return JsonDocument::decode((string) repositoryContent()->raw('rules/starter'));
}

/** The draft of the rules of the world. @return array<string, mixed> */
function worldDraft(): array
{
    return ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail()->draftDocument();
}

/** The key of the road between two cities in the state of the page. */
function roadKey(array $roads, string $from, string $to): ?string
{
    foreach ($roads as $key => $road) {
        if (($road['from'] === $from && $road['to'] === $to) || ($road['from'] === $to && $road['to'] === $from)) {
            return (string) $key;
        }
    }

    return null;
}

/** @return list<string> the kind and the cities or roads of each note, in a line each */
function notesOf(WorldBoard $board): array
{
    return array_map(fn (array $note): string => $note['kind'].':'.implode(',', [...$note['cities'], ...$note['roads']]), $board->warnings(['asphalt' => 'Asfalt', 'gravel' => 'Żwir']));
}

describe('the board', function () {
    it('takes the scale of the map from its roads: the median of their kilometres over the length of their line', function () {
        // Lines of 100, 200 and 50 units with 10, 30 and 20 km: 0.1, 0.15 and 0.4 km a unit, so the median is 0.15.
        $board = WorldBoard::of(boardState(
            ['a' => [0, 100], 'b' => [100, 100], 'c' => [100, 300], 'd' => [150, 300]],
            [['a', 'b', 10], ['b', 'c', 30], ['c', 'd', 20]],
        ), Measures::metric());

        expect($board->kmPerUnit())->toBe(0.15)
            // From a to d is 250 units on the board: 37.5 km, which is 38.
            ->and($board->suggestedKm('a', 'd'))->toBe(38);
    });

    it('takes 0.2 km a unit when no road tells the scale, and never suggests less than a kilometre', function () {
        $board = WorldBoard::of(boardState(['a' => [0, 100], 'b' => [100, 100], 'c' => [101, 100]]), Measures::metric());

        expect($board->kmPerUnit())->toBe(WorldBoard::KM_PER_UNIT)
            ->and($board->suggestedKm('a', 'b'))->toBe(20)
            ->and($board->suggestedKm('b', 'c'))->toBe(1);
    });

    it('starts a new driver in the city of the first track, and draws a city with the level of its easiest track', function () {
        $tracks = [
            't1' => ['region' => 'b', 'surface' => 'gravel', 'min_player_level' => 1],
            't2' => ['region' => 'a', 'surface' => 'asphalt', 'min_player_level' => 8],
            't3' => ['region' => 'a', 'surface' => 'gravel', 'min_player_level' => 5],
        ];
        $board = WorldBoard::of(boardState(['a' => [10, 100], 'b' => [100, 100], 'c' => [200, 100]], [], $tracks), Measures::metric());

        expect($board->home)->toBe('b')
            ->and($board->cities['a'])->toMatchArray(['level' => 5, 'surface' => 'asphalt'])
            ->and($board->icon('b'))->toBe('home')
            ->and($board->icon('a'))->toBe('lock')
            ->and($board->icon('c'))->toBe('flag');
    });

    it('keeps a place inside the strip of the board that the map screen shows', function () {
        expect(WorldBoard::clamp(-5, 10))->toBe(['x' => 0, 'y' => 60])
            ->and(WorldBoard::clamp(400.4, 400))->toBe(['x' => 390, 'y' => 360])
            ->and(WorldBoard::clamp(120.6, 200.2))->toBe(['x' => 121, 'y' => 200]);
    });
});

describe('the notes of the board', function () {
    it('have nothing to say about the map of the repository', function () {
        $board = WorldBoard::of(WorldForm::toState(publishedWorld()), Measures::metric());

        expect(notesOf($board))->toBe([]);
    });

    it('see what is wrong with the map of the server: cities cut by the edge, two on each other, names on each other and roads over a city', function () {
        $state = WorldForm::toState(publishedWorld());

        foreach ($state['regions'] as $index => $region) {
            $state['regions'][$index]['map'] = ['zlomowo' => ['x' => 15, 'y' => 347], 'kruszyn' => ['x' => 16, 'y' => 268], 'suchy-pas' => ['x' => 16, 'y' => 177], 'wilcza-przelecz' => ['x' => 19, 'y' => 81], 'autodrom' => ['x' => 54, 'y' => 83]][$region['slug']];
        }

        $notes = notesOf(WorldBoard::of($state, Measures::metric()));

        // The roads are keyed by their place in the list here: 2 is Kruszyn – Wilcza Przełęcz and 3 Kruszyn – Autodrom.
        expect($notes)->toBe([
            'edge:zlomowo', 'edge:kruszyn', 'edge:suchy-pas', 'edge:wilcza-przelecz', 'edge:autodrom',
            'labels:zlomowo,kruszyn',
            'overlap:wilcza-przelecz,autodrom',
            'through:suchy-pas,2', 'through:suchy-pas,3',
        ]);
    });

    it('see two roads that cross away from a city, and not two that meet in one', function () {
        $board = WorldBoard::of(boardState(
            ['a' => [100, 100], 'b' => [300, 300], 'c' => [300, 100], 'd' => [100, 300]],
            [['a', 'b', 10], ['c', 'd', 10], ['a', 'c', 10]],
        ), Measures::metric());

        expect(notesOf($board))->toBe(['crossing:road-0,road-1']);
    });

    it('call a city that no road reaches from the start an error', function () {
        $tracks = ['t1' => ['region' => 'a', 'surface' => 'asphalt', 'min_player_level' => 1]];
        $warnings = WorldBoard::of(boardState(['a' => [100, 100], 'b' => [300, 100], 'c' => [200, 300]], [['a', 'b', 10]], $tracks), Measures::metric())->warnings();

        expect($warnings)->toHaveCount(1)
            ->and($warnings[0])->toMatchArray(['kind' => 'unreachable', 'error' => true, 'cities' => ['c'], 'params' => ['city' => 'C', 'home' => 'A']]);
    });
});

describe('the map of the world page', function () {
    it('says under the map that nothing is wrong, and what is once a city is dragged to the edge', function () {
        signedInAdmin();

        $page = Livewire::test(WorldPage::class)
            ->assertSee(__('admin.world.warn_title'))
            ->assertSee(__('admin.world.warn_none'));

        $page->call('moveCity', 'kruszyn', 5, 185)
            ->assertDontSee(__('admin.world.warn_none'))
            ->assertSee(__('admin.world.warn.edge', ['city' => 'Kruszyn']));
    });

    it('draws every city with what the game writes under it, and every road', function () {
        signedInAdmin();

        Livewire::test(WorldPage::class)
            ->assertSeeHtml('data-test="world-city-zlomowo"')
            ->assertSeeHtml('data-test="world-city-autodrom"')
            ->assertSeeHtml('data-road="zlomowo-kruszyn"')
            ->assertSeeHtml('data-road="kruszyn-autodrom"')
            ->assertSee('LV 3+ · Żwir')
            ->assertSee(__('admin.world.map_tab'));
    });

    it('moves a dragged city, keeps it inside the strip the map screen shows, and saves its place', function () {
        signedInAdmin();

        $page = Livewire::test(WorldPage::class)
            ->call('moveCity', 'kruszyn', 200, 150)
            ->call('moveCity', 'autodrom', 500, 10);

        $places = array_column(array_values($page->get('data.regions')), 'map', 'slug');

        expect($places['kruszyn'])->toBe(['x' => 200, 'y' => 150])
            ->and($places['autodrom'])->toBe(['x' => 390, 'y' => 60]);

        $page->call('save')->assertHasNoFormErrors();

        expect(worldDraft()['regions']['kruszyn']['map'])->toBe(['x' => 200, 'y' => 150])
            ->and(worldDraft()['regions']['autodrom']['map'])->toBe(['x' => 390, 'y' => 60]);
    });

    it('joins two cities with a road of the length the scale of the map gives it, with no city in the middle', function () {
        signedInAdmin();

        $page = Livewire::test(WorldPage::class)->call('addRoad', 'suchy-pas', 'wilcza-przelecz');
        $key = roadKey($page->get('data.roads'), 'suchy-pas', 'wilcza-przelecz');

        // The four roads of the repository make 0.192 km a unit, and the line from Suchy Pas to Wilcza Przełęcz is 177 units long.
        expect($key)->not->toBeNull()
            ->and($page->get("data.roads.{$key}"))->toBe(['from' => 'suchy-pas', 'to' => 'wilcza-przelecz', 'km' => 34]);

        $page->call('save')->assertHasNoFormErrors();

        expect(worldDraft()['roads'])->toContain(['from' => 'suchy-pas', 'to' => 'wilcza-przelecz', 'km' => 34]);
    });

    it('keeps one road between two cities, and joins no city to itself or to one that is not there', function () {
        signedInAdmin();

        $page = Livewire::test(WorldPage::class);
        $roads = $page->get('data.roads');

        $page->call('addRoad', 'kruszyn', 'zlomowo');
        $page->call('addRoad', 'kruszyn', 'kruszyn');
        $page->call('addRoad', 'kruszyn', 'nowhere');

        expect($page->get('data.roads'))->toEqual($roads);
    });

    it('takes a road away, so the roads can be laid out as a chain instead of a star', function () {
        signedInAdmin();

        $page = Livewire::test(WorldPage::class);
        $page->call('removeRoad', roadKey($page->get('data.roads'), 'kruszyn', 'autodrom'));
        $page->call('removeRoad', roadKey($page->get('data.roads'), 'kruszyn', 'wilcza-przelecz'));
        $page->call('addRoad', 'suchy-pas', 'wilcza-przelecz');
        $page->call('addRoad', 'wilcza-przelecz', 'autodrom');
        $page->call('save')->assertHasNoFormErrors()->assertNotified(__('admin.definitions.saved'));

        $pairs = array_map(fn (array $road): string => "{$road['from']}-{$road['to']}", worldDraft()['roads']);

        expect($pairs)->toBe(['zlomowo-kruszyn', 'kruszyn-suchy-pas', 'suchy-pas-wilcza-przelecz', 'wilcza-przelecz-autodrom']);
    });

    it('writes the length of a new road in the unit of the game, and keeps whole kilometres in the draft', function () {
        signedInAdmin();
        $rules = JsonDocument::decode((string) repositoryContent()->raw('rules/starter'));
        $rules['units'] = Measures::preset('nautical')->toRules();
        Livewire::test(RulesJsonPage::class)->set('data.json', JsonDocument::encode($rules))->call('save')->assertHasNoFormErrors();

        $page = Livewire::test(WorldPage::class)->call('addRoad', 'suchy-pas', 'wilcza-przelecz');
        $key = roadKey($page->get('data.roads'), 'suchy-pas', 'wilcza-przelecz');

        // 34 km are 18.358531 nautical miles.
        expect($page->get("data.roads.{$key}.km"))->toBe(Measures::preset('nautical')->travel->inBox(34_000));

        $page->call('save')->assertHasNoFormErrors();

        expect(worldDraft()['roads'])->toContain(['from' => 'suchy-pas', 'to' => 'wilcza-przelecz', 'km' => 34]);
    });
});
