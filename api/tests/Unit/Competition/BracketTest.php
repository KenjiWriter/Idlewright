<?php

declare(strict_types=1);

use App\Domain\Competition\Bracket;
use App\Domain\Competition\LuckTable;
use App\Domain\Competition\MatchEngine;
use App\Domain\Competition\MatchResult;

/** @return list<array{id: int, rating: int}> */
function bracketEntrants(int $count): array
{
    return array_map(static fn (int $i): array => ['id' => $i, 'rating' => 1_000 + (($i * 37) % 11) * 25], range(1, $count));
}

/** @return list<int> the ids that are in a pair */
function pairedIds(array $pairs): array
{
    $ids = [];

    foreach ($pairs as $pair) {
        foreach ($pair as $id) {
            if ($id !== null) {
                $ids[] = $id;
            }
        }
    }

    return $ids;
}

/** @return list<int> the ids of the entrants from the best seed down */
function seedOrder(array $entrants): array
{
    usort($entrants, static fn (array $a, array $b): int => [$b['rating'], $a['id']] <=> [$a['rating'], $b['id']]);

    return array_column($entrants, 'id');
}

it('finds the size of a bracket: the next power of two, never below the minimum', function () {
    expect(Bracket::sizeFor(5, 8))->toBe(8)
        ->and(Bracket::sizeFor(8, 8))->toBe(8)
        ->and(Bracket::sizeFor(9, 8))->toBe(16)
        ->and(Bracket::sizeFor(17, 8))->toBe(32)
        ->and(fn () => Bracket::sizeFor(5, 6))->toThrow(InvalidArgumentException::class);
});

it('draws every entrant exactly once, and the same draw again for the same seed', function (int $count, int $size) {
    $entrants = bracketEntrants($count);
    $draw = Bracket::draw($entrants, $size, 'seed-a');

    expect($draw)->toHaveCount(intdiv($size, 2))
        ->and(pairedIds($draw))->toHaveCount($count)
        ->and(array_unique(pairedIds($draw)))->toHaveCount($count)
        ->and(Bracket::draw($entrants, $size, 'seed-a'))->toBe($draw);
})->with([[8, 8], [16, 16], [32, 32], [5, 8], [6, 8], [7, 8], [10, 16], [13, 16], [17, 32], [29, 32]]);

it('gives the byes to the best seeds, one bye to a pair', function (int $count, int $size) {
    $entrants = bracketEntrants($count);
    $byes = $size - $count;
    $draw = Bracket::draw($entrants, $size, 'seed-b');

    $withBye = [];

    foreach ($draw as $pair) {
        if ($pair[1] === null) {
            $withBye[] = $pair[0];
        }
    }

    $expected = array_slice(seedOrder($entrants), 0, $byes);
    sort($withBye);
    sort($expected);

    expect($withBye)->toBe($expected);
})->with([[5, 8], [6, 8], [7, 8], [9, 16], [11, 16], [15, 16], [17, 32], [24, 32]]);

it('seeds the best two into opposite halves and the next two into different quarters', function (string $seed) {
    $entrants = bracketEntrants(16);
    $ranked = seedOrder($entrants);
    $draw = Bracket::draw($entrants, 16, $seed);
    $pairOf = [];

    foreach ($draw as $index => $pair) {
        $pairOf[$pair[0]] = $index;
        $pairOf[$pair[1]] = $index;
    }

    [$first, $second, $third, $fourth] = array_map(static fn (int $id): int => $pairOf[$id], array_slice($ranked, 0, 4));
    $quarter = static fn (int $pair): int => intdiv($pair, 2);
    $half = static fn (int $pair): int => intdiv($pair, 4);

    expect($half($first))->not->toBe($half($second))
        ->and($quarter($third))->not->toBe($quarter($fourth))
        ->and(count(array_unique([$quarter($first), $quarter($second), $quarter($third), $quarter($fourth)])))->toBe(4);
})->with(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);

it('draws another bracket from another seed', function () {
    $entrants = bracketEntrants(16);
    $draws = [];

    foreach (['a', 'b', 'c', 'd', 'e'] as $seed) {
        $draws[json_encode(Bracket::draw($entrants, 16, $seed))] = true;
    }

    expect(count($draws))->toBeGreaterThan(3);
});

it('refuses a bracket that is half empty or has the same id twice', function () {
    expect(fn () => Bracket::draw(bracketEntrants(8), 32, 's'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Bracket::draw(bracketEntrants(9), 8, 's'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Bracket::draw([['id' => 1, 'rating' => 1_000], ['id' => 1, 'rating' => 1_100], ['id' => 2, 'rating' => 1_000]], 4, 's'))->toThrow(InvalidArgumentException::class);
});

/** The match of a tournament in the test: the entrant with the higher id has the quicker laps. */
function bracketMatch(): Closure
{
    $luck = LuckTable::fromFile(dirname(__DIR__, 4).'/content/tables/luck-ppm.json');
    $runs = [['track' => 'street', 'weather' => 'dry'], ['track' => 'gravel', 'weather' => 'dry'], ['track' => 'street', 'weather' => 'dry']];

    return static function (int|string $a, int|string $b, int $round, int $slot) use ($luck, $runs): MatchResult {
        $laps = static fn (int|string $id): array => ['street' => ['dry' => 80_000 - (int) $id * 500], 'gravel' => ['dry' => 100_000 - (int) $id * 600]];

        return MatchEngine::play($laps($a), $laps($b), $runs, 'event-seed', "{$round}:{$slot}", $luck, 20_000);
    };
}

it('plays a bracket to one champion, every match once, and says how far everyone got', function (int $count, int $size) {
    $entrants = bracketEntrants($count);
    $result = Bracket::run(Bracket::draw($entrants, $size, 'seed'), bracketMatch());
    $rounds = (int) round(log($size, 2));

    expect($result->roundCount())->toBe($rounds)
        ->and($result->reached)->toHaveCount($count)
        ->and($result->reached[$result->champion])->toBe($rounds + 1)
        ->and(count(array_filter($result->reached, static fn (int $round): bool => $round === $rounds + 1)))->toBe(1);

    $matches = 0;

    foreach ($result->rounds as $round) {
        foreach ($round as $match) {
            $matches += $match['result'] === null ? 0 : 1;
        }
    }

    // Everyone but the champion loses exactly one match.
    expect($matches)->toBe($count - 1);
})->with([[8, 8], [16, 16], [32, 32], [6, 8], [7, 8], [11, 16], [20, 32]]);

it('plays the same bracket again with the same champion, rounds and margins', function () {
    $draw = Bracket::draw(bracketEntrants(16), 16, 'seed');
    $first = Bracket::run($draw, bracketMatch());
    $again = Bracket::run($draw, bracketMatch());

    expect($again->champion)->toBe($first->champion)
        ->and($again->reached)->toBe($first->reached)
        ->and($again->marginMs)->toBe($first->marginMs);
});

it('ranks the entrants from the champion down, and tells those who lost in the same round apart by their margin and then by the order given', function () {
    $entrants = bracketEntrants(8);
    $result = Bracket::run(Bracket::draw($entrants, 8, 'seed'), bracketMatch());
    $order = array_column($entrants, 'id');
    $ranking = $result->ranking($order);

    expect($ranking)->toHaveCount(8)
        ->and($ranking[0])->toBe($result->champion)
        ->and($result->reached[$ranking[1]])->toBe(3);

    $reached = array_map(static fn (int $id): int => $result->reached[$id], $ranking);
    $sorted = $reached;
    rsort($sorted);

    expect($reached)->toBe($sorted)
        ->and($result->ranking($order))->toBe($ranking);
});
