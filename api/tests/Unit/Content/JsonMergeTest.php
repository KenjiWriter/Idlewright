<?php

declare(strict_types=1);

use App\Content\Rebase\Absent;
use App\Content\Rebase\JsonMerge;

/** Merges three documents written as JSON and gives the result as JSON again, with the merge to look at its conflicts. */
function threeWay(string $base, string $panel, string $files, string $prefer = JsonMerge::PANEL): array
{
    $merge = new JsonMerge($prefer);
    $merged = $merge->merge(JsonMerge::decode($base), JsonMerge::decode($panel), JsonMerge::decode($files));

    return [$merged === Absent::Value ? null : json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION), $merge->conflicts()];
}

describe('a merge of objects', function () {
    it('keeps a change of the panel and a change of the files in different places', function () {
        [$merged, $conflicts] = threeWay('{"fuel":200,"map":{"x":78,"y":290}}', '{"fuel":200,"map":{"x":15,"y":290}}', '{"fuel":210,"map":{"x":78,"y":290}}');

        expect($merged)->toBe('{"fuel":210,"map":{"x":15,"y":290}}')->and($conflicts)->toBe([]);
    });

    it('takes a change made the same way on both sides without a conflict', function () {
        expect(threeWay('{"a":1}', '{"a":2}', '{"a":2}'))->toBe(['{"a":2}', []]);
    });

    it('lists a place both sides changed in two ways, and gives it to the side that is preferred', function () {
        [$panel, $conflicts] = threeWay('{"a":1,"b":1}', '{"a":2,"b":1}', '{"a":3,"b":5}');
        [$files] = threeWay('{"a":1,"b":1}', '{"a":2,"b":1}', '{"a":3,"b":5}', JsonMerge::FILES);

        expect($panel)->toBe('{"a":2,"b":5}')
            ->and($files)->toBe('{"a":3,"b":5}')
            ->and($conflicts)->toBe([['path' => '/a', 'base' => 1, 'panel' => 2, 'files' => 3]]);
    });

    it('adds the keys the files add and leaves out a key one side took out and the other did not change', function () {
        [$merged, $conflicts] = threeWay('{"a":1,"b":2,"c":3}', '{"a":1,"c":3}', '{"a":1,"b":2,"d":4}');

        expect($merged)->toBe('{"a":1,"d":4}')->and($conflicts)->toBe([]);
    });

    it('sees a key taken out on one side and changed on the other as a conflict', function () {
        [, $conflicts] = threeWay('{"a":1}', '{}', '{"a":2}');

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]['path'])->toBe('/a')
            ->and($conflicts[0]['panel'])->toBe(Absent::Value);
    });

    it('does not count the order of the keys or a whole number written with a point as a change', function () {
        expect(JsonMerge::same(JsonMerge::decode('{"a":1,"b":{"c":2.0}}'), JsonMerge::decode('{"b":{"c":2},"a":1}')))->toBeTrue()
            ->and(JsonMerge::same(JsonMerge::decode('[1,2]'), JsonMerge::decode('[2,1]')))->toBeFalse()
            ->and(JsonMerge::same(JsonMerge::decode('{}'), JsonMerge::decode('[]')))->toBeFalse();
    });

    it('writes an empty object back as an object', function () {
        [$merged] = threeWay('{"a":1,"reward":{}}', '{"a":2,"reward":{}}', '{"a":1,"reward":{},"b":3}');

        expect($merged)->toBe('{"a":2,"reward":{},"b":3}');
    });
});

describe('a merge of lists', function () {
    it('merges the steps by their key: a step changed in the panel and a step the files put in both stay, in their places', function () {
        $base = '{"steps":[{"key":"a","sender":"1"},{"key":"b","sender":"2"},{"key":"c","sender":"3"}]}';
        $panel = '{"steps":[{"key":"a","sender":"K"},{"key":"b","sender":"2"},{"key":"c","sender":"3"}]}';
        $files = '{"steps":[{"key":"a","sender":"1"},{"key":"b","sender":"2"},{"key":"new","sender":"4"},{"key":"c","sender":"3"}]}';

        [$merged, $conflicts] = threeWay($base, $panel, $files);

        expect($merged)->toBe('{"steps":[{"key":"a","sender":"K"},{"key":"b","sender":"2"},{"key":"new","sender":"4"},{"key":"c","sender":"3"}]}')
            ->and($conflicts)->toBe([]);
    });

    it('keeps a step the panel put in and the order the panel gave, with a step the files took out gone', function () {
        $base = '{"steps":[{"key":"a"},{"key":"b"},{"key":"c"}]}';
        $panel = '{"steps":[{"key":"c"},{"key":"a"},{"key":"mine"},{"key":"b"}]}';
        $files = '{"steps":[{"key":"a"},{"key":"c"}]}';

        expect(threeWay($base, $panel, $files)[0])->toBe('{"steps":[{"key":"c"},{"key":"a"},{"key":"mine"}]}');
    });

    it('names a conflict in a step by the key of the step', function () {
        [, $conflicts] = threeWay('{"steps":[{"key":"a","goal":"x"}]}', '{"steps":[{"key":"a","goal":"y"}]}', '{"steps":[{"key":"a","goal":"z"}]}');

        expect(array_column($conflicts, 'path'))->toBe(['/steps/a/goal']);
    });

    it('takes a list without names as one value', function () {
        [$merged, $conflicts] = threeWay('{"pay":[150,125,110]}', '{"pay":[160,125,110]}', '{"pay":[150,125,110,100]}');

        expect($merged)->toBe('{"pay":[160,125,110]}')
            ->and(array_column($conflicts, 'path'))->toBe(['/pay']);
    });
});

describe('a merge of documents', function () {
    it('takes a document that only the files have, and leaves out one the files took out that the panel did not change', function () {
        $merge = new JsonMerge;

        expect($merge->merge(Absent::Value, Absent::Value, JsonMerge::decode('{"a":1}')))->toEqual(JsonMerge::decode('{"a":1}'))
            ->and($merge->merge(JsonMerge::decode('{"a":1}'), JsonMerge::decode('{"a":1}'), Absent::Value))->toBe(Absent::Value)
            ->and($merge->conflicts())->toBe([]);
    });
});
