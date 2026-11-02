<?php

declare(strict_types=1);

use App\Content\Bridge\ChangeBlockParser;
use App\Content\Bridge\DocumentPointer;

// Taking the block of changes out of what a person pasted: the whole answer of the AI or the block alone, one block only, nothing trusted. And the places in a document
// that a block names, as pointers.

require_once __DIR__.'/../../Support/BridgeHelpers.php';

describe('taking the block out of the answer', function () {
    it('reads the fenced block called ride-changes and nothing else of the answer', function () {
        $parsed = (new ChangeBlockParser)->parse(bridgeFenced(bridgeBlockOf()));

        expect($parsed['issues'])->toBe([])
            ->and($parsed['block']['ops'][0])->toMatchArray(['op' => 'scale', 'percent' => 12])
            ->and($parsed['block']['summary'])->toBe('Engines of tier 3: 12% more power');
    });

    it('also reads a block that was fenced as json, and one that is bare JSON', function () {
        $fenced = (new ChangeBlockParser)->parse(bridgeFenced(bridgeBlockOf(), 'json'));
        $bare = (new ChangeBlockParser)->parse(json_encode(bridgeBlockOf()));

        expect($fenced['issues'])->toBe([])->and($fenced['block'])->not->toBeNull()
            ->and($bare['issues'])->toBe([])->and($bare['block'])->not->toBeNull();
    });

    it('refuses an answer with no block, and one with two', function () {
        $none = (new ChangeBlockParser)->parse("I would raise the power by 12%, shall I?\n");
        $two = (new ChangeBlockParser)->parse(bridgeFenced(bridgeBlockOf())."\n".bridgeFenced(bridgeBlockOf(['summary' => 'Another'])));

        expect($none['block'])->toBeNull()->and($none['issues'][0]->code)->toBe('bad_block')
            ->and($two['block'])->toBeNull()->and($two['issues'][0]->code)->toBe('bad_block')
            ->and($two['issues'][0]->message)->toContain('more than one');
    });

    it('refuses text that is not JSON, JSON that is too deep, and text that is too long', function () {
        $broken = (new ChangeBlockParser)->parse("```ride-changes\n{\"format\": \"ride-changes/1\",\n```");
        $deep = (new ChangeBlockParser)->parse(str_repeat('{"a":', 40).'1'.str_repeat('}', 40));
        $long = (new ChangeBlockParser)->parse(str_repeat('x', ChangeBlockParser::MAX_BYTES + 1));

        expect($broken['issues'][0]->code)->toBe('bad_json')
            ->and($deep['issues'][0]->code)->toBe('bad_json')
            ->and($long['issues'][0]->code)->toBe('too_large');
    });

    it('says the format is not understood, instead of listing what the schema does not like', function () {
        $parsed = (new ChangeBlockParser)->parse(bridgeFenced(bridgeBlockOf(['format' => 'ride-changes/7'])));

        expect($parsed['block'])->toBeNull()
            ->and($parsed['issues'])->toHaveCount(1)
            ->and($parsed['issues'][0]->code)->toBe('unknown_format');
    });
});

describe('the schema of the block', function () {
    it('refuses what the format does not have: a key, a unit, a document, a percent that would make a number nothing', function (array $over, string $fragment) {
        $parsed = (new ChangeBlockParser)->parse(bridgeFenced(bridgeBlockOf($over)));

        expect($parsed['block'])->toBeNull()
            ->and(collect($parsed['issues'])->pluck('code')->unique()->all())->toBe(['schema'])
            ->and(collect($parsed['issues'])->map->message->implode(' '))->toContain($fragment);
    })->with([
        'a key of its own' => [['scope' => ['part']], 'Additional'],
        'no operations' => [['ops' => []], 'items'],
        'an operation that does not exist' => [['ops' => [['op' => 'delete', 'target' => ['kind' => 'part', 'slug' => 'x']]]], ''],
        'a unit that does not exist' => [['ops' => [['op' => 'set', 'target' => ['rules' => 'starter'], 'path' => '/premium/vip/coins', 'value' => 100, 'unit' => 'ppm']]], ''],
        'a document of the rules that is not one' => [['ops' => [['op' => 'set', 'target' => ['rules' => 'economy'], 'path' => '/a/b', 'value' => 1, 'unit' => 'plain']]], ''],
        'a percent that would take all of a number' => [['ops' => [['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/fuel/price_cents_per_liter', 'percent' => -100]]], ''],
        'a pointer with an escape' => [['ops' => [['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/fuel/price~1x', 'percent' => 5]]], ''],
        'a scale with neither target nor select' => [['ops' => [['op' => 'scale', 'path' => '/stats/power_kw', 'percent' => 5]]], ''],
        'a scale with both' => [['ops' => [['op' => 'scale', 'target' => ['rules' => 'starter'], 'select' => ['kind' => 'part', 'where' => ['tier' => 1]], 'path' => '/stats/power_kw', 'percent' => 5]]], ''],
        'a snapshot that is not one' => [['base' => ['pack' => '2026.11.1', 'snapshot' => 'xyz']], ''],
    ]);

    it('refuses more than two hundred operations', function () {
        $ops = array_fill(0, 201, ['op' => 'scale', 'target' => ['rules' => 'starter'], 'path' => '/fuel/price_cents_per_liter', 'percent' => 1]);

        expect((new ChangeBlockParser)->parse(bridgeFenced(bridgeBlockOf(['ops' => $ops])))['block'])->toBeNull();
    });
});

describe('the pointer', function () {
    it('splits and joins the names, escapes included', function () {
        expect(DocumentPointer::split('/a/b~1c/d~0e'))->toBe(['a', 'b/c', 'd~e'])
            ->and(DocumentPointer::join(['a', 'b/c', 'd~e', 3]))->toBe('/a/b~1c/d~0e/3');
    });

    it('refuses what is not a pointer', function (string $pointer) {
        expect(fn () => DocumentPointer::split($pointer))->toThrow(InvalidArgumentException::class);
    })->with(['no slash' => 'a/b', 'empty name' => '/a//b', 'nothing' => '']);

    it('reads and writes a place, and leaves the rest of the document alone, empty objects included', function () {
        $document = ['stats' => ['power_kw' => 86.0, 'mass' => 95], 'list' => [10, 20], 'empty' => new stdClass];

        $changed = DocumentPointer::set($document, ['stats', 'power_kw'], 96.3);
        $listed = DocumentPointer::set($document, ['list', 1], 25);

        expect(DocumentPointer::get($changed, ['stats', 'power_kw']))->toBe(96.3)
            ->and($changed['stats']['mass'])->toBe(95)
            ->and($changed['empty'])->toBeInstanceOf(stdClass::class)
            ->and($document['stats']['power_kw'])->toBe(86.0)
            ->and($listed['list'])->toBe([10, 25])
            ->and(DocumentPointer::has($document, ['stats', 'nothing']))->toBeFalse()
            ->and(DocumentPointer::has($document, ['list', '1']))->toBeTrue()
            ->and(fn () => DocumentPointer::set($document, ['stats', 'nothing'], 1))->toThrow(InvalidArgumentException::class);
    });

    it('matches a pattern where a star is exactly one name', function () {
        expect(DocumentPointer::matches(['tracks', '*', 'length_m'], ['tracks', 'zlomowo-street', 'length_m']))->toBeTrue()
            ->and(DocumentPointer::matches(['tracks', '*', 'length_m'], ['tracks', 'a', 'b', 'length_m']))->toBeFalse()
            ->and(DocumentPointer::matches(['list', '*'], ['list', 2]))->toBeTrue()
            ->and(DocumentPointer::matches(['tracks', 'x'], ['tracks', 'y']))->toBeFalse();
    });
});
