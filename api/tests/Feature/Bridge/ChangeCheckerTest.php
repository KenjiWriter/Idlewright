<?php

declare(strict_types=1);

use App\Content\Bridge\BridgeIssue;
use App\Content\Bridge\ChangeCheck;
use App\Content\Bridge\ChangeChecker;
use App\Content\Bridge\ContentSnapshot;
use App\Models\ContentDefinition;
use App\Models\Game;
use App\Services\ContentDrafts;

// What a block of changes comes to against the content of the game: the selectors expanded, the arithmetic and the rounding done by the server, every number held to its
// schema, and the whole content judged as it would be. Nothing is written by any of it.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

beforeEach(fn () => contentPacks()->import(repositoryContent(), activate: true, checkTables: false));

/** @param list<array<string, mixed>> $ops @param array<string, mixed> $over */
function bridgeMessage(array $ops, array $over = []): string
{
    return bridgeFenced([
        'format' => 'ride-changes/1',
        'game' => Game::query()->where('is_default', true)->value('slug'),
        'base' => ['pack' => contentPacks()->active()->number, 'snapshot' => app(ContentSnapshot::class)->current()],
        'summary' => 'A change for the test',
        'ops' => $ops,
        ...$over,
    ]);
}

/** @param list<array<string, mixed>> $ops @param list<string>|null $scope */
function bridgeCheck(array $ops, ?array $scope = null, array $over = []): ChangeCheck
{
    return app(ChangeChecker::class)->check(bridgeMessage($ops, $over), $scope);
}

/** @return list<string> */
function bridgeCodes(ChangeCheck $check): array
{
    return array_map(static fn (BridgeIssue $issue): string => $issue->code, $check->issues);
}

function bridgeSetCoins(int|float $value, string $unit = 'plain'): array
{
    return ['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/coins', 'value' => $value, 'unit' => $unit];
}

function bridgeEngines(int $percent = 12): array
{
    return ['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'tier' => 3]], 'path' => '/stats/power_kw', 'percent' => $percent];
}

describe('scaling a selection of the cars or the parts', function () {
    it('picks the engines of tier 3 and gives each the power it would have, rounded to the decimals the numbers have', function () {
        $check = bridgeCheck([bridgeEngines(12)]);

        // The V6 has 158.12 kW, so the numbers of the set are rounded to two decimals: 115.0 becomes 128.8 and 158.12 becomes 177.09.
        $documents = collect(['engine-r4-2-0-t3', 'engine-v6-2-5-t3']);
        $expected = $documents->mapWithKeys(fn (string $slug): array => ["part:{$slug}" => round(repositoryDocument("parts/{$slug}")['stats']['power_kw'] * 1.12, 2)]);

        expect($check->errors())->toBe([])
            ->and($check->ok())->toBeTrue()
            ->and($check->changes)->toHaveCount(2)
            ->and(collect($check->changes)->pluck('document')->sort()->values()->all())->toBe(['part:engine-r4-2-0-t3', 'part:engine-v6-2-5-t3'])
            ->and(collect($check->changes)->mapWithKeys(fn ($change): array => [$change->document => $change->after])->all())->toEqual($expected->all())
            ->and($check->changes[0]->pointer())->toBe('/stats/power_kw')
            ->and($check->changes[0]->label)->toContain('Power kw')
            ->and(array_keys($check->documents))->toEqualCanonicalizing(['part:engine-r4-2-0-t3', 'part:engine-v6-2-5-t3'])
            ->and($check->documents['part:engine-v6-2-5-t3']['stats']['engine_mass_kg'])->toBe(repositoryDocument('parts/engine-v6-2-5-t3')['stats']['engine_mass_kg']);
    });

    it('writes nothing: the definitions are as they were', function () {
        $before = ContentDefinition::query()->where('slug', 'engine-v6-2-5-t3')->first();

        bridgeCheck([bridgeEngines(12)]);

        $after = ContentDefinition::query()->where('slug', 'engine-v6-2-5-t3')->first();

        expect($after->draft)->toBeNull()->and($after->updated_at->equalTo($before->updated_at))->toBeTrue();
    });

    it('says plainly when the selector picks nothing, and when the path is not a number of what it picks', function () {
        $none = bridgeCheck([['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'tier' => 99]], 'path' => '/stats/power_kw', 'percent' => 5]]);
        $path = bridgeCheck([['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'tier' => 3]], 'path' => '/stats/wings', 'percent' => 5]]);
        $text = bridgeCheck([['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'tier' => 3]], 'path' => '/slot', 'percent' => 5]]);

        expect(bridgeCodes($none))->toContain('no_match')
            ->and(bridgeCodes($path))->toContain('unknown_path')
            ->and(bridgeCodes($text))->toContain('unknown_path')
            ->and($none->ok())->toBeFalse();
    });

    it('selects by a range, by a set, and by a field inside an object', function () {
        $range = bridgeCheck([['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'tier' => ['gte' => 2, 'lte' => 3]]], 'path' => '/price_cents', 'percent' => 10]]);
        $set = bridgeCheck([['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => ['in' => ['tires']], 'tier' => 1]], 'path' => '/price_cents', 'percent' => 10]]);
        $inside = bridgeCheck([['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'engine/layout' => 'R4', 'tier' => 2]], 'path' => '/stats/power_kw', 'percent' => 10]]);

        $tires = collect(repositoryContent()->keys())->filter(fn (string $key): bool => str_starts_with($key, 'parts/'))->map(fn (string $key): array => repositoryDocument($key))->filter(fn (array $part): bool => $part['slot'] === 'tires' && $part['tier'] === 1);

        expect(collect($range->changes)->pluck('document')->every(fn (string $document): bool => str_starts_with($document, 'part:engine-')))->toBeTrue()
            ->and(count($range->changes))->toBeGreaterThan(2)
            ->and($set->changes)->toHaveCount($tires->count())
            ->and($inside->changes)->not->toBeEmpty();
    });

    it('rounds a whole number half away from zero and says so, and keeps a number inside its limits', function () {
        // 45 000 cents and 0.01% more is 45 004.5, which is 45 005.
        $check = bridgeCheck([['op' => 'scale', 'target' => ['kind' => 'part', 'slug' => 'all-season-tires-t1'], 'path' => '/price_cents', 'percent' => 0.01]]);

        expect($check->errors())->toBe([])
            ->and($check->changes[0]->after)->toBe(45005)
            ->and($check->changes[0]->notes)->toContain('rounded')
            ->and($check->changes[0]->unit->suffix())->toBe('$')
            ->and($check->changes[0]->afterText())->toBe('450.05 $');
    });

    it('is refused a percent with more than two decimals, and says when nothing changes', function () {
        $precise = bridgeCheck([['op' => 'scale', 'target' => ['kind' => 'part', 'slug' => 'all-season-tires-t1'], 'path' => '/price_cents', 'percent' => 12.345]]);
        $nothing = bridgeCheck([['op' => 'scale', 'target' => ['kind' => 'part', 'slug' => 'all-season-tires-t1'], 'path' => '/price_cents', 'percent' => 0]]);

        expect(bridgeCodes($precise))->toBe(['percent_precision'])
            ->and(bridgeCodes($nothing))->toContain('no_change')
            ->and($nothing->changes)->toBe([])
            ->and($nothing->ok())->toBeFalse();
    });
});

describe('the numbers of the rules', function () {
    it('sets a number in the unit the studio shows it in, and keeps what was live next to what it was', function () {
        $check = bridgeCheck([bridgeSetCoins(120)]);

        expect($check->errors())->toBe([])
            ->and($check->changes)->toHaveCount(1)
            ->and($check->changes[0])->toMatchObject(['document' => 'rules:starter', 'before' => 150, 'after' => 120, 'published' => 150])
            ->and($check->changes[0]->label)->toContain('Vip')
            ->and($check->changes[0]->percent())->toBe(-20.0)
            ->and($check->documents['rules:starter']['premium']['vip']['coins'])->toBe(120)
            ->and($check->documents['rules:starter']['fuel'])->toBe(['price_cents_per_liter' => 200, 'reference_power_kw' => 97]);
    });

    it('takes a percent as a percent and turns it into the ppm of the document', function () {
        $check = bridgeCheck([['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/cash_bonus_ppm', 'value' => 25, 'unit' => 'percent']]);

        expect($check->errors())->toBe([])->and($check->changes[0]->after)->toBe(250_000)->and($check->changes[0]->beforeText())->toBe('20 %');
    });

    it('refuses a value in the wrong unit, a fraction of a whole number, and a number outside its limits', function () {
        $unit = bridgeCheck([bridgeSetCoins(120, 'percent')]);
        $fraction = bridgeCheck([bridgeSetCoins(119.5)]);
        $over = bridgeCheck([bridgeSetCoins(2_000_000)]);
        $under = bridgeCheck([bridgeSetCoins(0)]);

        expect(bridgeCodes($unit))->toBe(['unit_mismatch'])
            ->and($unit->issues[0]->message)->toContain('plain')
            ->and(bridgeCodes($fraction))->toBe(['not_integer'])
            ->and(bridgeCodes($over))->toBe(['above_max'])
            ->and($over->issues[0]->message)->toContain('1000000')
            ->and(bridgeCodes($under))->toBe(['below_min'])
            ->and($over->documents)->toBe([]);
    });

    it('refuses a place that is not a number the catalog knows: a switch, a name, something that is not there', function () {
        $switch = bridgeCheck([['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/enabled', 'value' => 1, 'unit' => 'plain']]);
        $absent = bridgeCheck([['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/discount', 'value' => 1, 'unit' => 'plain']]);
        $star = bridgeCheck([['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/products/*/coins', 'value' => 1, 'unit' => 'plain']]);
        $gone = bridgeCheck([['op' => 'set', 'target' => ['kind' => 'part', 'slug' => 'no-such-part'], 'path' => '/price_cents', 'value' => 1, 'unit' => 'dollars']]);

        expect(bridgeCodes($switch))->toBe(['unknown_path'])
            ->and(bridgeCodes($absent))->toBe(['unknown_path'])
            ->and(bridgeCodes($star))->toBe(['wildcard_in_set'])
            ->and(bridgeCodes($gone))->toBe(['unknown_document']);
    });

    it('scales every number a star names, in one go', function () {
        $check = bridgeCheck([['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/premium/products/*/coins', 'percent' => 10]]);

        $products = repositoryDocument('rules/starter')['premium']['products'];

        expect($check->errors())->toBe([])
            ->and($check->changes)->toHaveCount(count($products))
            ->and(collect($check->changes)->map(fn ($change): array => $change->path)->all())->toEqual(array_map(fn (string $name): array => ['premium', 'products', $name, 'coins'], array_keys($products)));
    });

    it('does not change a price in real money, by name or by star', function () {
        $named = bridgeCheck([['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/infinity/price_minor', 'value' => 2499, 'unit' => 'plain']]);
        $scaled = bridgeCheck([['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/premium/products/coins-small/price_minor', 'percent' => 10]]);
        $star = bridgeCheck([['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/premium/products/coins-small/*', 'percent' => 10]]);

        expect(bridgeCodes($named))->toBe(['real_money_field'])
            ->and(bridgeCodes($scaled))->toBe(['real_money_field'])
            ->and(collect($star->changes)->pluck('path')->map(fn (array $path): string => end($path))->all())->toBe(['coins'])
            ->and(bridgeCodes($star))->toContain('skipped_real_money');
    });
});

describe('operations in order', function () {
    it('lets a later operation see what an earlier one did, and says it wrote the same place twice', function () {
        $check = bridgeCheck([bridgeSetCoins(100), ['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/coins', 'percent' => 10]]);

        expect($check->errors())->toBe([])
            ->and($check->changes)->toHaveCount(2)
            ->and([$check->changes[1]->before, $check->changes[1]->after])->toBe([100, 110])
            ->and($check->documents['rules:starter']['premium']['vip']['coins'])->toBe(110)
            ->and(bridgeCodes($check))->toContain('overwritten');
    });

    it('applies nothing of an operation that is refused in part, and still looks at the ones after it', function () {
        $check = bridgeCheck([
            ['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['slot' => 'engine', 'tier' => 3]], 'path' => '/tier', 'percent' => 900, 'id' => 'tiers'],
            bridgeSetCoins(120),
        ]);

        expect(bridgeCodes($check))->toContain('above_max')
            ->and($check->errors()[0]->opId)->toBe('tiers')
            ->and($check->errors()[0]->op)->toBe(1)
            ->and(collect($check->changes)->pluck('document')->all())->toBe(['rules:starter']);
    });

    it('limits what one block may change', function () {
        $ops = array_map(fn (int $i): array => ['op' => 'scale', 'select' => ['kind' => 'part', 'where' => ['tier' => ['gte' => 1]]], 'path' => '/price_cents', 'percent' => $i % 2 === 0 ? 1 : -1], range(1, 8));

        $check = bridgeCheck($ops);

        expect(bridgeCodes($check))->toContain('too_many')->and($check->ok())->toBeFalse();
    });
});

describe('a draft that is in the way', function () {
    it('goes on top of a draft, and keeps what is live next to it', function () {
        $part = repositoryDocument('parts/engine-v6-2-5-t3');
        $live = $part['stats']['power_kw'];
        $part['stats']['power_kw'] = $live + 10;

        app(ContentDrafts::class)->save(ContentDefinition::query()->where('slug', 'engine-v6-2-5-t3')->firstOrFail(), $part, anAdmin());

        $check = bridgeCheck([['op' => 'scale', 'target' => ['kind' => 'part', 'slug' => 'engine-v6-2-5-t3'], 'path' => '/stats/power_kw', 'percent' => 10]]);

        expect($check->changes[0])->toMatchObject(['before' => $live + 10, 'published' => $live])
            ->and($check->changes[0]->notes)->toContain('draft')
            ->and($check->touched['part:engine-v6-2-5-t3']['draft'])->toBeTrue()
            ->and($check->touched['part:engine-v6-2-5-t3']['saved_at'])->not->toBeNull();
    });
});

describe('what the instruction contained', function () {
    it('refuses a document that was not in it, by name or by kind', function () {
        $out = bridgeCheck([bridgeEngines(12)], ['rules:starter']);
        $byKind = bridgeCheck([bridgeEngines(12)], ['kind:part']);
        $byName = bridgeCheck([['op' => 'scale', 'target' => ['kind' => 'part', 'slug' => 'all-season-tires-t1'], 'path' => '/price_cents', 'percent' => 5]], ['part:all-season-tires-t1']);

        expect(bridgeCodes($out))->toContain('out_of_scope')
            ->and($byKind->errors())->toBe([])
            ->and($byName->errors())->toBe([]);
    });
});

describe('the block as a whole', function () {
    it('is refused for another game, whatever it holds', function () {
        $check = bridgeCheck([bridgeSetCoins(120)], null, ['game' => 'another-game']);

        expect(bridgeCodes($check))->toBe(['wrong_game'])->and($check->changes)->toBe([])->and($check->ok())->toBeFalse();
    });

    it('is told when it was made from other content than there is now', function () {
        $pack = bridgeCheck([bridgeSetCoins(120)], null, ['base' => ['pack' => '2020.1.1', 'snapshot' => '00000000']]);

        expect($pack->errors())->toBe([])
            ->and(bridgeCodes($pack))->toContain('base_pack', 'base_changed')
            ->and($pack->ok())->toBeTrue();
    });

    it('has the snapshot change when a draft is saved, and only then', function () {
        $snapshot = app(ContentSnapshot::class);
        $first = $snapshot->current();

        expect($snapshot->current())->toBe($first);

        $part = repositoryDocument('parts/engine-v6-2-5-t3');
        $part['price_cents'] += 100;
        app(ContentDrafts::class)->save(ContentDefinition::query()->where('slug', 'engine-v6-2-5-t3')->firstOrFail(), $part, anAdmin());

        expect($snapshot->current())->not->toBe($first)->and($snapshot->current())->toMatch('/^[0-9a-f]{8}$/');
    });

    it('has the whole content judged as it would be, and refuses a change the numbers of the content do not hold with', function () {
        // A car's tier decides the level of the driver it is sold at (3 x tier - 3): a tier of 3 with a level of 3 is not a car the dealer can sell.
        $check = bridgeCheck([['op' => 'scale', 'target' => ['kind' => 'vehicle', 'slug' => 'rdzak-gt-4'], 'path' => '/tier', 'percent' => 50]]);

        expect($check->changes)->toHaveCount(1)
            ->and(bridgeCodes($check))->toContain('invalid_content')
            ->and($check->ok())->toBeFalse()
            ->and($check->repairMessage())->toContain('[invalid_content]')->toContain('Fix only the points below');
    });

    it('gives the AI a message that says what to fix and nothing else', function () {
        $check = bridgeCheck([bridgeSetCoins(120, 'percent'), ['op' => 'set', 'id' => 'two', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/days', 'value' => 99999, 'unit' => 'plain']]);

        $message = $check->repairMessage();

        expect($message)->toContain('1. op 1 [unit_mismatch] at rules:starter /premium/vip/coins')
            ->and($message)->toContain('2. op 2 (id two) [above_max]')
            ->and(bridgeCheck([bridgeSetCoins(120)])->repairMessage())->toBe('');
    });
});
