<?php

declare(strict_types=1);

use App\Content\Definitions\JsonDocument;
use App\Content\Studio\Parameter;
use App\Content\Studio\ParameterCatalog;
use App\Content\Studio\Unit;
use App\Filament\Pages\StudioPage;
use App\Filament\Resources\Games\GameResource;
use App\Models\ContentDefinition;
use Livewire\Livewire;

// What the studio shows of a number that has to be above a limit, of a number in a list, and of the tire matrix; what it does with the numbers that were typed when a
// switch is flipped; and the slug that the wizard makes out of a name.

function fixesRules(): array
{
    return JsonDocument::decode((string) repositoryContent()->raw('rules/starter'));
}

function fixesDraft(): array
{
    return ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail()->draftDocument();
}

/** @return array{0: int, 1: Parameter} */
function fixesParameter(string $section, string $pointer): array
{
    foreach (ParameterCatalog::ofTheGame()->parameters(fixesRules(), $section) as $position => $parameter) {
        if ($parameter->pointer() === $pointer) {
            return [$position, $parameter];
        }
    }

    throw new RuntimeException("No number at {$pointer}.");
}

beforeEach(function () {
    contentPacks()->import(repositoryContent(), checkTables: false, activate: true);
    app()->setLocale('pl');
});

describe('a number that has to be above a limit', function () {
    it('has a lowest value that the studio states, one step above the limit', function () {
        [, $base] = fixesParameter('player', '/player/xp_base');
        [, $exponent] = fixesParameter('player', '/player/xp_exponent');

        // The schema says the number is above 0, not 0 or more.
        expect($base->min)->toBe(0.01)
            ->and($exponent->min)->toBe(0.01);
    });

    it('is not cut down to 0 by a 0 that was typed, but to the lowest value', function () {
        [$position] = fixesParameter('player', '/player/xp_base');

        $document = ParameterCatalog::ofTheGame()->apply(fixesRules(), 'player', [$position => 0]);

        expect($document['player']['xp_base'])->toBe(0.01);
    });
});

describe('a number in a list', function () {
    it('says which place it is when the catalog names all the places alike', function () {
        [, $first] = fixesParameter('market', '/market/exchange/defects/0/weights/0');
        [, $third] = fixesParameter('market', '/market/exchange/defects/1/weights/2');

        expect($first->pattern)->toBe('/market/exchange/defects/*/weights/*')
            ->and($first->position)->toBe('#1')
            ->and($third->position)->toBe('#3');
    });

    it('says it beside the name from the catalog in the field, so that the weights of the bands are not all called the same', function () {
        signedInAdmin();

        Livewire::test(StudioPage::class)
            ->fillForm(['section' => 'market', 'simple' => false])
            ->assertSee('Waga liczby usterek · #1')
            ->assertSee('Waga liczby usterek · #2');
    });

    it('says nothing of the place for a number that its pattern names alone', function () {
        [, $price] = fixesParameter('fuel', '/fuel/price_cents_per_liter');

        expect($price->position)->toBe('');
    });
});

describe('the tire matrix', function () {
    it('is counted in percent, like the factors of a drive', function () {
        [$position, $cell] = fixesParameter('tire_matrix', '/tire_matrix/offroad/asphalt_dry');

        expect($cell->unit)->toBe(Unit::Percent)
            ->and($cell->display())->toBe(85);

        $document = ParameterCatalog::ofTheGame()->apply(fixesRules(), 'tire_matrix', [$position => 90]);

        expect($document['tire_matrix']['offroad']['asphalt_dry'])->toBe(900_000);
    });
});

describe('the switches of the studio', function () {
    it('keep the numbers that were typed and not saved when the simple mode is flipped, and the bar still shows them as a change', function () {
        signedInAdmin();
        [$position] = fixesParameter('fuel', '/fuel/price_cents_per_liter');

        $page = Livewire::test(StudioPage::class)
            ->fillForm(['section' => 'fuel', 'simple' => true, 'values' => ["p{$position}" => 3.5]])
            ->set('data.simple', false)
            ->assertFormSet(['simple' => false, "values.p{$position}" => 3.5]);

        // Nothing was saved by flipping the switch.
        expect(fixesRules()['fuel']['price_cents_per_liter'])->not->toBe(350);

        $page->call('save');

        expect(fixesDraft()['fuel']['price_cents_per_liter'])->toBe(350);
    });

    it('keep the numbers that were typed when sliders are given up for fields', function () {
        signedInAdmin();
        [$position] = fixesParameter('fuel', '/fuel/price_cents_per_liter');

        Livewire::test(StudioPage::class)
            ->fillForm(['section' => 'fuel', 'sliders' => true, 'values' => ["p{$position}" => 3.5]])
            ->set('data.sliders', false)
            ->assertFormSet(['sliders' => false, "values.p{$position}" => 3.5]);
    });
});

describe('the slug that the wizard makes of a name', function () {
    it('starts with a letter, as the slug of a game has to', function () {
        expect(GameResource::slugFrom('2048 Racing Club'))->toBe('racing-club')
            ->and(GameResource::slugFrom('007'))->toBe('')
            ->and(GameResource::slugFrom('Gwiezdne Statki'))->toBe('gwiezdne-statki');
    });

    it('is not longer than 32 characters, and does not end in a dash', function () {
        $slug = GameResource::slugFrom('Wielkie Wyścigi Gwiezdnych Statków Dalekiego Zasięgu');

        expect(strlen($slug))->toBeLessThanOrEqual(32)
            ->and($slug)->toMatch(GameResource::SLUG)
            ->and($slug)->not->toEndWith('-');
    });
});
