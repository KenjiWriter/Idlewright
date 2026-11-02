<?php

declare(strict_types=1);

use App\Content\Bridge\ApplyRefused;
use App\Content\Bridge\Brief;
use App\Content\Bridge\BriefBuilder;
use App\Content\Bridge\BriefSelection;
use App\Content\Bridge\ChangeApplier;
use App\Content\Bridge\ChangeCheck;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ChangeReverter;
use App\Models\ContentDefinition;
use App\Models\Game;
use App\Services\ContentDrafts;

// The texts and the new cars and parts: a text changes in one language and the others stay, a translation that nobody has read is marked, a new car is a copy of one that
// exists with whole fields replaced, and both are kept as drafts and undone like numbers are.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** @param list<array<string, mixed>> $ops */
function textsCheck(array $ops, ?Brief $brief = null): ChangeCheck
{
    $brief ??= bridgeTheBrief();

    return app(ChangeChecker::class)->check(bridgeFenced([
        'format' => 'ride-changes/1',
        'game' => (string) Game::query()->where('is_default', true)->value('slug'),
        'base' => ['pack' => $brief->pack, 'snapshot' => $brief->snapshot, 'brief' => $brief->code],
        'summary' => 'A change for the test',
        'ops' => $ops,
    ]));
}

/** @return list<string> */
function textsCodes(ChangeCheck $check): array
{
    return array_map(static fn ($issue): string => $issue->code, $check->issues);
}

function nameOp(string $locale, string $value, string $slug = 'rdzak-gt-4', string $path = '/name'): array
{
    return ['op' => 'text', 'target' => ['kind' => 'vehicle', 'slug' => $slug], 'path' => $path, 'locale' => $locale, 'value' => $value];
}

describe('a text in one language', function () {
    it('changes that language and leaves the others, and says what it was live and now', function () {
        $check = textsCheck([nameOp('pl', 'Rdzak GT-4 Turbo')]);
        $name = $check->documents['vehicle:rdzak-gt-4']['name'];

        expect($check->errors())->toBe([])
            ->and($check->ok())->toBeTrue()
            ->and($check->changes[0])->toMatchObject(['type' => 'text', 'before' => 'Rdzak GT-4', 'after' => 'Rdzak GT-4 Turbo', 'published' => 'Rdzak GT-4'])
            ->and($check->changes[0]->path)->toBe(['name', 'pl'])
            ->and($check->changes[0]->notes)->toBe([])
            ->and($check->changes[0]->percent())->toBe(0.0)
            ->and($name['pl'])->toBe('Rdzak GT-4 Turbo')
            ->and($name['en'])->toBe(repositoryDocument('vehicles/rdzak-gt-4')['name']['en'])
            ->and($name['fr'])->toBe(repositoryDocument('vehicles/rdzak-gt-4')['name']['fr']);
    });

    it('marks a translation that is not in English or Polish as one that nobody has read, and adds a language that had none', function () {
        $check = textsCheck([nameOp('de', 'Rdzak GT-4 Turbo')]);

        expect($check->changes[0]->notes)->toContain('unreviewed')
            ->and($check->ok())->toBeTrue();
    });

    it('is refused for a place that is not a text, for markup and for a link, and says when nothing changes', function () {
        $number = textsCheck([nameOp('pl', 'x', path: '/price_cents')]);
        $markup = textsCheck([nameOp('pl', '<b>Turbo</b>')]);
        $link = textsCheck([nameOp('pl', 'See https://example.com for more')]);
        $same = textsCheck([nameOp('pl', 'Rdzak GT-4')]);

        expect(textsCodes($number))->toBe(['not_text'])
            ->and(textsCodes($markup))->toBe(['text_markup'])
            ->and(textsCodes($link))->toBe(['text_markup'])
            ->and(textsCodes($same))->toContain('no_change')
            ->and($same->ok())->toBeFalse();
    });

    it('changes a text of the rules in a section the instruction held, and not in one it did not', function () {
        $op = ['op' => 'text', 'target' => ['rules' => 'starter'], 'path' => '/premium/products/coins-small/name', 'locale' => 'en', 'value' => 'Tiny pack'];

        $held = textsCheck([$op], bridgeTheBrief(new BriefSelection(vehicles: false, parts: false, sections: ['premium'], simple: false, locale: 'en')));
        $other = textsCheck([$op], bridgeTheBrief(new BriefSelection(vehicles: false, parts: false, sections: ['fuel'], simple: false, locale: 'en')));

        expect($held->errors())->toBe([])
            ->and($held->changes[0]->after)->toBe('Tiny pack')
            ->and(textsCodes($other))->toContain('out_of_scope');
    });
});

describe('a new car or part', function () {
    it('is a copy of one that exists without its picture, with whole fields replaced, and passes the checks of the content', function () {
        $check = textsCheck([['op' => 'create', 'kind' => 'vehicle', 'from' => 'rdzak-gt-4', 'slug' => 'rdzak-gt-9', 'fields' => ['name' => ['en' => 'Rdzak GT-9', 'pl' => 'Rdzak GT-9'], 'price_cents' => 2650000]]]);
        $car = $check->documents['vehicle:rdzak-gt-9'];

        expect($check->errors())->toBe([])
            ->and($check->ok())->toBeTrue()
            ->and($check->created)->toBe(['vehicle:rdzak-gt-9'])
            ->and($check->changes[0])->toMatchObject(['type' => 'create', 'after' => 'Rdzak GT-9'])
            ->and($check->changes[0]->notes)->toBe(['new'])
            ->and($car['slug'])->toBe('rdzak-gt-9')
            ->and($car['price_cents'])->toBe(2650000)
            ->and($car['name'])->toBe(['en' => 'Rdzak GT-9', 'pl' => 'Rdzak GT-9'])
            ->and($car['power_kw'])->toBe(repositoryDocument('vehicles/rdzak-gt-4')['power_kw'])
            ->and($car)->not->toHaveKey('image')
            ->and($check->touched['vehicle:rdzak-gt-9'])->toBe(['draft' => false, 'saved_at' => null]);
    });

    it('is refused for a slug that is taken, a car to copy that is not there, a picture, and a kind the instruction did not hold', function () {
        $fields = ['name' => ['en' => 'X', 'pl' => 'X']];

        $taken = textsCheck([['op' => 'create', 'kind' => 'vehicle', 'from' => 'rdzak-gt-4', 'slug' => 'rdzak-gt-4', 'fields' => $fields]]);
        $missing = textsCheck([['op' => 'create', 'kind' => 'vehicle', 'from' => 'no-such-car', 'slug' => 'x-1', 'fields' => $fields]]);
        $picture = textsCheck([['op' => 'create', 'kind' => 'vehicle', 'from' => 'rdzak-gt-4', 'slug' => 'x-2', 'fields' => [...$fields, 'image' => str_repeat('a', 64).'.png']]]);
        $kind = textsCheck([['op' => 'create', 'kind' => 'vehicle', 'from' => 'rdzak-gt-4', 'slug' => 'x-3', 'fields' => $fields]], bridgeTheBrief(new BriefSelection(vehicles: false)));

        expect(textsCodes($taken))->toBe(['slug_taken'])
            ->and(textsCodes($missing))->toBe(['unknown_document'])
            ->and(array_unique(textsCodes($picture)))->toBe(['schema'])
            ->and(textsCodes($kind))->toBe(['out_of_scope']);
    });

    it('cannot be changed by another operation of the same block', function () {
        $check = textsCheck([
            ['op' => 'create', 'kind' => 'vehicle', 'from' => 'rdzak-gt-4', 'slug' => 'rdzak-gt-9', 'fields' => ['price_cents' => 2650000]],
            ['op' => 'scale', 'target' => ['kind' => 'vehicle', 'slug' => 'rdzak-gt-9'], 'path' => '/price_cents', 'percent' => 10],
        ]);

        expect(textsCodes($check))->toBe(['unknown_document']);
    });

    it('is refused by the checks of the content when the car does not hold together, as a number would be', function () {
        // A car of tier 3 is for a driver of level 6 and up, and the copy says level 3.
        $check = textsCheck([['op' => 'create', 'kind' => 'vehicle', 'from' => 'rdzak-gt-4', 'slug' => 'rdzak-gt-9', 'fields' => ['tier' => 3]]]);

        expect(textsCodes($check))->toContain('invalid_content')->and($check->ok())->toBeFalse();
    });
});

describe('kept as drafts and undone', function () {
    it('makes the new car as a draft that was never published, and undoing it deletes it', function () {
        $text = bridgeCanned('new-car', $brief = bridgeTheBrief());
        $admin = anAdmin();
        $seen = app(ChangeChecker::class)->check($text)->touched;

        $exchange = app(ChangeApplier::class)->apply($text, $admin, $seen);
        $car = ContentDefinition::query()->where('kind', 'vehicle')->where('slug', 'rdzak-gt-9')->firstOrFail();

        expect($car->status)->toBe(ContentDefinition::STATUS_DRAFT)
            ->and($car->draftDocument()['price_cents'])->toBe(2650000)
            ->and($car->publishedDocument())->toBeNull()
            ->and($exchange->resolved['created'])->toBe(['vehicle:rdzak-gt-9']);

        app(ChangeReverter::class)->revert($exchange, $admin);

        expect(ContentDefinition::query()->where('slug', 'rdzak-gt-9')->exists())->toBeFalse()
            ->and($exchange->fresh()->status)->toBe('reverted');
    });

    it('does not undo a new car that somebody has worked on since', function () {
        $text = bridgeCanned('new-car', bridgeTheBrief());
        $admin = anAdmin();
        $exchange = app(ChangeApplier::class)->apply($text, $admin, app(ChangeChecker::class)->check($text)->touched);

        $car = ContentDefinition::query()->where('slug', 'rdzak-gt-9')->firstOrFail();
        $document = $car->draftDocument();
        $document['price_cents'] += 1;
        app(ContentDrafts::class)->save($car, $document, $admin);

        expect(fn () => app(ChangeReverter::class)->revert($exchange, $admin))->toThrow(ApplyRefused::class)
            ->and(ContentDefinition::query()->where('slug', 'rdzak-gt-9')->exists())->toBeTrue();
    });

    it('keeps the renamed car as a draft with the other languages as they were, and undoing it puts the draft back', function () {
        $text = bridgeCanned('rename', bridgeTheBrief());
        $admin = anAdmin();
        $exchange = app(ChangeApplier::class)->apply($text, $admin, app(ChangeChecker::class)->check($text)->touched);

        $car = ContentDefinition::query()->where('slug', 'rdzak-gt-4')->firstOrFail();

        expect($car->draftDocument()['name']['pl'])->toBe('Rdzak GT-4 Turbo')
            ->and($car->draftDocument()['name']['en'])->toBe(repositoryDocument('vehicles/rdzak-gt-4')['name']['en']);

        app(ChangeReverter::class)->revert($exchange, $admin);

        expect($car->fresh()->hasDraft())->toBeFalse();
    });
});

describe('what the instruction says about them', function () {
    it('tells the AI to ask, not to invent, and says what text and create are', function () {
        $text = app(BriefBuilder::class)->build(new BriefSelection(locale: 'en'), 'code1234')->text;

        expect($text)->toContain('Never invent a slug, a path or a number that is not in the data')
            ->and($text)->toContain('Do not send the block in the same message in which you ask questions')
            ->and($text)->toContain('`create` makes a new car or part')
            ->and($text)->toContain('`text` sets the text of ONE `locale`')
            ->and($text)->toContain('"op": "create"');
    });

    it('lists the texts with all their languages only when asked', function () {
        $without = app(BriefBuilder::class)->build(new BriefSelection(sections: ['premium'], locale: 'en'), 'code1234')->text;
        $with = app(BriefBuilder::class)->build(new BriefSelection(sections: ['premium'], simple: false, locale: 'en', texts: true), 'code1234')->text;

        expect($without)->not->toContain("\nTEXTS ")
            ->and($with)->toContain("\nTEXTS {\"kind\":\"vehicle\"}")
            ->and($with)->toContain('["rdzak-gt-4",{"/name":{"en":"Rdzak GT-4","pl":"Rdzak GT-4","fr":"Rdzak GT-4","de":"Rdzak GT-4","pt-BR":"Rdzak GT-4"}')
            ->and($with)->toContain("\nTEXTS {\"document\":\"starter\"}")
            ->and($with)->toContain('"/premium/products/coins-small/name"');
    });
});
