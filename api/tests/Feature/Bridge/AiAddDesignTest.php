<?php

declare(strict_types=1);

use App\Content\Bridge\BriefBuilder;
use App\Content\Bridge\BriefSelection;
use App\Content\Bridge\ChangeApplier;
use App\Content\Bridge\ChangeCheck;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ChangeReverter;
use App\Content\Bridge\SchemaCollections;
use App\Content\Definitions\JsonDocument;
use App\Models\ContentDefinition;
use App\Models\Game;

// A new entry in a collection of the rules, and the manner of designing a whole game: where an entry can be added, what is refused, that it is kept as a draft and undone, and
// what the instruction says in the manner of the design.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** @param list<array<string, mixed>> $ops @param list<string>|null $scope */
function addCheck(array $ops, ?BriefSelection $selection = null): ChangeCheck
{
    $brief = bridgeTheBrief($selection ?? new BriefSelection(locale: 'en', simple: false, sections: ['premium']));

    return app(ChangeChecker::class)->check(bridgeFenced([
        'format' => 'ride-changes/1',
        'game' => (string) Game::query()->where('is_default', true)->value('slug'),
        'base' => ['pack' => $brief->pack, 'snapshot' => $brief->snapshot, 'brief' => $brief->code],
        'summary' => 'A change for the test',
        'ops' => $ops,
    ]));
}

/** @return list<string> */
function addCodes(ChangeCheck $check): array
{
    return array_map(static fn ($issue): string => $issue->code, $check->issues);
}

function refillOp(string $key = 'refill-10', array $value = ['charges' => 10, 'coins' => 16]): array
{
    return ['op' => 'add', 'target' => ['rules' => 'starter'], 'path' => '/premium/autorefill/packs', 'key' => $key, 'value' => $value];
}

describe('where an entry can be added', function () {
    it('knows the collections of the rules and tells them from the sections that have a fixed set of properties', function () {
        $rules = JsonDocument::decode((string) repositoryContent()->raw('rules/starter'));

        expect(SchemaCollections::isCollection('starter', ['premium', 'products']))->toBeTrue()
            ->and(SchemaCollections::isCollection('starter', ['premium', 'autorefill', 'packs']))->toBeTrue()
            ->and(SchemaCollections::isCollection('starter', ['tracks']))->toBeTrue()
            ->and(SchemaCollections::isCollection('starter', ['premium', 'vip']))->toBeFalse()
            ->and(SchemaCollections::isCollection('starter', ['premium', 'vip', 'coins']))->toBeFalse()
            ->and(SchemaCollections::isCollection('starter', ['nothing', 'here']))->toBeFalse()
            ->and(SchemaCollections::of('starter', $rules))->toContain('/premium/products', '/premium/autorefill/packs', '/tracks');
    });
});

describe('adding an entry', function () {
    it('adds an entry beside the ones that are there, and says what it is, without keeping anything', function () {
        $check = addCheck([refillOp()]);
        $packs = $check->documents['rules:starter']['premium']['autorefill']['packs'];

        expect($check->errors())->toBe([])
            ->and($check->ok())->toBeTrue()
            ->and(array_keys($packs))->toBe(['refill-1', 'refill-5', 'refill-20', 'refill-10'])
            ->and($packs['refill-10'])->toBe(['charges' => 10, 'coins' => 16])
            ->and($check->changes[0])->toMatchObject(['type' => 'add', 'after' => 'refill-10'])
            ->and($check->changes[0]->notes)->toBe(['new'])
            ->and($check->changes[0]->pointer())->toBe('/premium/autorefill/packs/refill-10')
            ->and(ContentDefinition::query()->whereNotNull('draft')->count())->toBe(0);
    });

    it('is refused for a name that is taken, a place that is not a collection, and a price in real money', function () {
        $taken = addCheck([refillOp('refill-5')]);
        $fixed = addCheck([['op' => 'add', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip', 'key' => 'extra', 'value' => ['days' => 3]]]);
        $money = addCheck([['op' => 'add', 'target' => ['rules' => 'starter'], 'path' => '/premium/products', 'key' => 'coins-mega', 'value' => ['coins' => 5000, 'price_minor' => 9999]]]);

        expect(addCodes($taken))->toBe(['key_taken'])
            ->and(addCodes($fixed))->toBe(['not_a_collection'])
            ->and(addCodes($money))->toBe(['real_money_field']);
    });

    it('is refused for a section the instruction did not hold, and by the checks of the content for an entry that does not fit', function () {
        $other = addCheck([refillOp()], new BriefSelection(locale: 'en', simple: false, sections: ['fuel']));
        $wrong = addCheck([refillOp('refill-10', ['charges' => 'many', 'coins' => 16])]);
        $big = addCheck([refillOp('refill-500', ['charges' => 500, 'coins' => 16])]);

        expect(addCodes($other))->toContain('out_of_scope')
            ->and($wrong->ok())->toBeFalse()
            ->and(addCodes($wrong))->toContain('invalid_content')
            ->and(addCodes($big))->toContain('invalid_content');
    });

    it('is kept as a draft of the rules like a number is, and undoing it takes the entry out again', function () {
        $brief = bridgeTheBrief(new BriefSelection(locale: 'en', simple: false, sections: ['premium']));
        $text = bridgeFenced([
            'format' => 'ride-changes/1',
            'game' => (string) Game::query()->where('is_default', true)->value('slug'),
            'base' => ['pack' => $brief->pack, 'snapshot' => $brief->snapshot, 'brief' => $brief->code],
            'summary' => 'A bigger pack of refills',
            'ops' => [refillOp()],
        ]);
        $admin = anAdmin();

        $exchange = app(ChangeApplier::class)->apply($text, $admin, app(ChangeChecker::class)->check($text)->touched);
        $rules = ContentDefinition::query()->where('kind', 'rules')->where('slug', 'starter')->firstOrFail();

        expect($rules->draftDocument()['premium']['autorefill']['packs'])->toHaveKey('refill-10');

        app(ChangeReverter::class)->revert($exchange, $admin);

        expect($rules->fresh()->hasDraft())->toBeFalse();
    });
});

describe('the manner of designing a game', function () {
    it('lists the collections of the sections it holds, as the places an entry can go', function () {
        $text = app(BriefBuilder::class)->build(new BriefSelection(sections: ['premium'], simple: false, locale: 'en'), 'code1234')->text;

        expect($text)->toContain("\nCOLLECTIONS [")
            ->and($text)->toContain('"/premium/products"')
            ->and($text)->toContain('"/premium/autorefill/packs"')
            ->and($text)->toContain('`add` puts a NEW entry into a collection of the rules');
    });

    it('is a different conversation: in stages, one block to a stage, about the words first, with the texts in the data', function () {
        $change = app(BriefBuilder::class)->build(new BriefSelection(locale: 'en'), 'code1234');
        $design = app(BriefBuilder::class)->build(new BriefSelection(locale: 'en', design: true), 'code1234');

        expect($change->text)->toContain('# Assistant for changing the parameters of one game')
            ->and($change->text)->not->toContain("\nTEXTS ")
            ->and($design->text)->toContain('# Assistant for designing the content of a game')
            ->and($design->text)->toContain('Work in STAGES, one kind of thing at a time')
            ->and($design->text)->toContain('Never put two stages in one message')
            ->and($design->text)->toContain('Ask about the words first')
            ->and($design->text)->toContain("\nTEXTS {\"kind\":\"vehicle\"}")
            ->and($design->text)->toContain('The format of the block of changes')
            ->and((new BriefSelection(design: true))->key())->not->toBe((new BriefSelection)->key());
    });
});
