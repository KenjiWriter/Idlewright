<?php

declare(strict_types=1);

use App\Content\Definitions\ThreadDiff;

// What differs between the sections of two threads, said as moved, changed, added and taken away.

$straight = static fn (int|float $length): array => ['type' => 'straight', 'length_m' => $length];
$corner = static fn (int $radius, int $angle, string $turn = 'right'): array => ['type' => 'corner', 'radius_m' => $radius, 'angle_deg' => $angle, 'turn' => $turn];

it('has nothing to say about threads that are the same, and takes 10 and 10.0 for the same number', function () use ($straight, $corner) {
    $sections = [$straight(400), $corner(60, 180), $straight(400), $corner(60, 180)];

    expect(ThreadDiff::between($sections, $sections))->toBe([])
        ->and(ThreadDiff::between([$straight(400)], [$straight(400.0)]))->toBe([]);
});

it('says a section that moved up the list was moved, and the ones it passed were not touched', function () use ($straight, $corner) {
    $old = [$straight(100), $corner(30, 90), $straight(200), $corner(40, 90), $straight(300)];
    $new = [$straight(100), $straight(300), $corner(30, 90), $straight(200), $corner(40, 90)];

    $operations = ThreadDiff::between($old, $new);

    expect($operations)->toHaveCount(1)
        ->and($operations[0])->toMatchArray(['op' => ThreadDiff::MOVED, 'from' => 5, 'to' => 2]);
});

it('says which boxes of a section changed, with what they were', function () use ($straight, $corner) {
    $old = [$straight(100), $corner(30, 90)];
    $new = [$straight(150), $corner(30, 120, 'left')];

    $operations = ThreadDiff::between($old, $new);

    expect($operations)->toHaveCount(2)
        ->and($operations[0])->toMatchArray(['op' => ThreadDiff::CHANGED, 'from' => 1, 'to' => 1, 'fields' => ['length_m' => [100, 150]]])
        ->and($operations[1])->toMatchArray(['op' => ThreadDiff::CHANGED, 'from' => 2, 'to' => 2, 'fields' => ['angle_deg' => [90, 120], 'turn' => ['right', 'left']]]);
});

it('says a section was added and a section of another kind was taken away', function () use ($straight, $corner) {
    $old = [$straight(100), $corner(30, 90), $straight(200)];
    $new = [$straight(100), $straight(200), $straight(150)];

    $ops = collect(ThreadDiff::between($old, $new))->keyBy('op');

    expect($ops->keys()->sort()->values()->all())->toBe(['added', 'removed'])
        ->and($ops['removed'])->toMatchArray(['from' => 2, 'to' => null])
        ->and($ops['added'])->toMatchArray(['from' => null, 'to' => 3]);
});

it('says every section is added when there was no thread', function () use ($straight) {
    $operations = ThreadDiff::between([], [$straight(100), $straight(200)]);

    expect(array_column($operations, 'op'))->toBe(['added', 'added'])
        ->and(array_column($operations, 'to'))->toBe([1, 2]);
});
