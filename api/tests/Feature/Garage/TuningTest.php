<?php

declare(strict_types=1);

use App\Content\PackValidator;
use App\Content\Rules;
use App\Domain\Market\Tuning;
use App\Support\Locale;

/** The tuning of the rules of the game, as the pack that is live has it. */
function tuningRules(): array
{
    return app(Rules::class)->tuning();
}

function tuningDraw(array $changes = []): Tuning
{
    return new Tuning([...tuningRules(), ...$changes]);
}

/** @return array<string, mixed> the standard bonus of the tests: 5% give or take 2.3%, from 1% to 12% */
function tuningBonus(): array
{
    return ['mean_ppm' => 50_000, 'sigma_ppm' => 23_000, 'min_ppm' => 10_000, 'max_ppm' => 120_000];
}

describe('the draw from the bell curve', function () {
    it('is the sum of twelve even draws less its mean: whole numbers in six sigmas, the same for the same seed', function () {
        $first = Tuning::z('seed', 'gravel-grip', 1);

        expect(Tuning::z('seed', 'gravel-grip', 1))->toBe($first)
            // The key and the try are part of the draw, so a try never repeats another.
            ->and(Tuning::z('seed', 'gravel-grip', 2))->not->toBe($first)
            ->and(Tuning::z('seed', 'dry-grip', 1))->not->toBe($first);

        foreach (range(1, 300) as $i) {
            expect(abs(Tuning::z("s-{$i}", 'dry-grip', $i)))->toBeLessThanOrEqual(6_000_000);
        }
    });

    it('has the mean 0, the spread 1 and the tail of a normal curve to two sigmas (20 000 draws)', function () {
        $sum = 0;
        $squares = 0;
        $high = 0;
        $draws = 20_000;

        foreach (range(1, $draws) as $i) {
            $z = Tuning::z("bell-{$i}", 'dry-grip', 1);
            $sum += $z;
            $squares += $z * $z;
            $high += $z >= 2_000_000 ? 1 : 0;
        }

        $mean = $sum / $draws / 1_000_000;
        $spread = sqrt($squares / $draws) / 1_000_000;

        expect(abs($mean))->toBeLessThan(0.03)
            ->and($spread)->toBeGreaterThan(0.97)->and($spread)->toBeLessThan(1.03)
            // 2.3% of a normal curve is above two sigmas.
            ->and($high / $draws * 100)->toBeGreaterThan(1.8)->and($high / $draws * 100)->toBeLessThan(2.8);
    });

    it('gives the mean for a draw of 0, stops at the least and the most, and lifts a draw to the floor of a new bonus', function () {
        $bonus = tuningBonus();

        expect(Tuning::amount($bonus, 0))->toBe(50_000)
            ->and(Tuning::amount($bonus, 6_000_000))->toBe(120_000)
            ->and(Tuning::amount($bonus, -6_000_000))->toBe(10_000)
            // Half a sigma above the mean, rounded to a ppm.
            ->and(Tuning::amount($bonus, 500_000))->toBe(61_500)
            // A floor of one sigma: no lower than the mean less one sigma, however low the draw.
            ->and(Tuning::amount($bonus, -4_000_000, 1_000_000))->toBe(27_000)
            ->and(Tuning::amount($bonus, 1_000_000, 1_000_000))->toBe(73_000);
    });

    it('is as rare as the exact tail says: the higher the amount, the more tries it takes (measured against 20 000 draws)', function () {
        $bonus = tuningBonus();
        $draws = 20_000;
        $atLeast = fn (int $ppm): int => count(array_filter(range(1, $draws), fn (int $i): bool => Tuning::amount($bonus, Tuning::z("tail-{$i}", 'dry-grip', 1)) >= $ppm));

        // 8% comes out about once in ten tries.
        expect($atLeast(80_000) / $draws)->toEqualWithDelta(Tuning::chanceAtLeast($bonus, 80_000), 0.01)
            ->and(Tuning::triesToReach($bonus, 80_000))->toBeGreaterThan(9.0)->and(Tuning::triesToReach($bonus, 80_000))->toBeLessThan(11.0)
            ->and(Tuning::triesToReach($bonus, 100_000))->toBeGreaterThan(60.0)->and(Tuning::triesToReach($bonus, 100_000))->toBeLessThan(80.0)
            ->and(Tuning::triesToReach($bonus, 110_000))->toBeGreaterThan(200.0)
            ->and(Tuning::triesToReach($bonus, 120_000))->toBeGreaterThan(1_000.0)
            // At or below the least every try gives it, above the most none does.
            ->and(Tuning::chanceAtLeast($bonus, 10_000))->toBe(1.0)
            ->and(Tuning::chanceAtLeast($bonus, 120_001))->toBe(0.0)
            ->and(Tuning::triesToReach($bonus, 120_001))->toBeNull();

        $last = 1.0;

        foreach (range(10_000, 120_000, 5_000) as $ppm) {
            $chance = Tuning::chanceAtLeast($bonus, $ppm);
            expect($chance)->toBeLessThanOrEqual($last);
            $last = $chance;
        }
    });

    it('moves the chances with the protection from bad luck: a mean a sigma higher makes 10% a one in six', function () {
        $bonus = tuningBonus();

        expect(Tuning::chanceAtLeast($bonus, 100_000, 1_000_000))->toBeGreaterThan(Tuning::chanceAtLeast($bonus, 100_000) * 5)
            ->and(tuningDraw(['pity_step_ppm' => 50_000, 'pity_max_ppm' => 1_000_000])->pity(10))->toBe(500_000)
            ->and(tuningDraw(['pity_step_ppm' => 50_000, 'pity_max_ppm' => 1_000_000])->pity(40))->toBe(1_000_000)
            // The rules of the game have it off.
            ->and(tuningDraw()->pity(40))->toBe(0);
    });

    it('makes the seed of a try from the secret, the driver, the part, the place and the try, and nothing else', function () {
        $seed = Tuning::seed('secret', 7, 42, 1, 3);

        expect($seed)->toBe(hash_hmac('sha256', 'tuning|7|42|1|3', 'secret'))
            ->and(Tuning::seed('secret', 7, 42, 1, 3))->toBe($seed)
            ->and(collect([Tuning::seed('other', 7, 42, 1, 3), Tuning::seed('secret', 8, 42, 1, 3), Tuning::seed('secret', 7, 43, 1, 3), Tuning::seed('secret', 7, 42, 2, 3), Tuning::seed('secret', 7, 42, 1, 4)])->unique()->count())->toBe(5)
            ->and(collect([Tuning::seed('other', 7, 42, 1, 3), Tuning::seed('secret', 8, 42, 1, 3)])->contains($seed))->toBeFalse();
    });
});

describe('the pool of a place', function () {
    it('is the bonuses that fit the slot and are on offer at the level, without the ones the part has at its other places', function () {
        $draw = tuningDraw();

        // At the first level only what the first level offers: brakes have the sprint bonus.
        expect(array_keys($draw->pool('brakes', 1)))->toBe(['sprint-brake'])
            // At level 4 the bonuses of the long tracks come in.
            ->and(array_keys($draw->pool('brakes', 4)))->toBe(['sprint-brake', 'endurance-wear', 'endurance-reliability'])
            ->and(array_keys($draw->pool('brakes', 4, ['sprint-brake'])))->toBe(['endurance-wear', 'endurance-reliability'])
            // A slot that no bonus fits at the level has an empty pool.
            ->and($draw->pool('tank', 1))->toBe([])
            ->and(array_keys($draw->pool('tires', 8)))->toContain('snow-grip')
            ->and(array_keys($draw->pool('tires', 7)))->not->toContain('snow-grip');
    });

    it('draws a bonus by weight, the same one for the same seed (4 000 draws)', function () {
        $draw = tuningDraw();
        $pool = $draw->pool('tires', 8);
        $total = array_sum(array_column($pool, 'weight'));
        $counts = array_fill_keys(array_keys($pool), 0);

        foreach (range(1, 4_000) as $i) {
            $counts[$draw->pick("pick-{$i}", $pool, 1)]++;
        }

        foreach ($pool as $key => $bonus) {
            expect($counts[$key] / 4_000)->toEqualWithDelta($bonus['weight'] / $total, 0.03, $key);
        }

        expect($draw->pick('same', $pool, 2))->toBe($draw->pick('same', $pool, 2))->and($draw->pick('same', [], 1))->toBeNull();
    });
});

describe('a try at a place', function () {
    it('fills an empty place with a bonus of the pool, with the amount of the draw and a first try counted', function () {
        $draw = tuningDraw();
        $place = $draw->attempt(null, 'sprint-brake', 'first', 'brakes', 1);

        expect($place['key'])->toBe('sprint-brake')
            ->and($place['tries'])->toBe(1)->and($place['fails'])->toBe(0)
            ->and($place['ppm'])->toBeGreaterThanOrEqual(27_000)->and($place['ppm'])->toBeLessThanOrEqual(120_000)
            // With no pick the pool decides: at the first level only one bonus fits brakes.
            ->and($draw->attempt(null, null, 'second', 'brakes', 1)['key'])->toBe('sprint-brake');
    });

    it('keeps the better of the two amounts when the bonus is the same, so a try never costs what the place has', function () {
        $draw = tuningDraw();
        $place = $draw->attempt(null, 'sprint-brake', 'seed-0', 'brakes', 1);
        $improved = 0;

        foreach (range(1, 400) as $i) {
            $next = $draw->attempt($place, 'sprint-brake', "seed-{$i}", 'brakes', 1);

            expect($next['key'])->toBe('sprint-brake')
                ->and($next['ppm'])->toBeGreaterThanOrEqual($place['ppm'])
                ->and($next['tries'])->toBe($place['tries'] + 1)
                // A try that helped starts the count of the ones that did not afresh.
                ->and($next['fails'])->toBe($next['ppm'] > $place['ppm'] ? 0 : $place['fails'] + 1);

            $improved += $next['ppm'] > $place['ppm'] ? 1 : 0;
            $place = $next;
        }

        // 400 tries took it up, and a few of them were enough: the best of many is near the top.
        expect($improved)->toBeGreaterThan(3)->and($place['ppm'])->toBeGreaterThan(95_000);
    });

    it('puts another bonus in the place, replacing the one it held, at no less than the floor below the mean', function () {
        $draw = tuningDraw();
        $held = ['key' => 'sprint-brake', 'ppm' => 118_000, 'tries' => 40, 'fails' => 12];

        foreach (range(1, 300) as $i) {
            $next = $draw->attempt($held, 'endurance-wear', "swap-{$i}", 'brakes', 4);

            expect($next['key'])->toBe('endurance-wear')
                // The mean of the bonus less one sigma, and never above its most.
                ->and($next['ppm'])->toBeGreaterThanOrEqual(27_000)->and($next['ppm'])->toBeLessThanOrEqual(120_000)
                ->and($next['tries'])->toBe(41)->and($next['fails'])->toBe(0);
        }
    });

    it('refuses a bonus that does not fit the slot, is not on offer yet, or is at another place of the part', function () {
        $draw = tuningDraw();

        expect($draw->attempt(null, 'dry-grip', 's', 'brakes', 1))->toBeNull()
            ->and($draw->attempt(null, 'snow-grip', 's', 'tires', 3))->toBeNull()
            ->and($draw->attempt(null, 'sprint-brake', 's', 'brakes', 1, ['sprint-brake']))->toBeNull()
            ->and($draw->attempt(null, 'nonsense', 's', 'brakes', 1))->toBeNull()
            ->and($draw->attempt(null, null, 's', 'tank', 1))->toBeNull();
    });

    it('is the same for the same seed on every call, and does not change what it was given', function () {
        $draw = tuningDraw();
        $held = ['key' => 'dry-grip', 'ppm' => 40_000, 'tries' => 2, 'fails' => 1];

        expect($draw->attempt($held, 'dry-grip', 'x', 'tires', 1))->toBe($draw->attempt($held, 'dry-grip', 'x', 'tires', 1))
            ->and($held)->toBe(['key' => 'dry-grip', 'ppm' => 40_000, 'tries' => 2, 'fails' => 1]);
    });

    it('moves the mean up after tries that did not help when the protection is on', function () {
        $without = tuningDraw();
        $with = tuningDraw(['pity_step_ppm' => 100_000, 'pity_max_ppm' => 1_000_000]);
        $held = ['key' => 'dry-grip', 'ppm' => 10_000, 'tries' => 30, 'fails' => 30];
        $sumWithout = 0;
        $sumWith = 0;

        foreach (range(1, 600) as $i) {
            $sumWithout += $without->attempt($held, 'dry-grip', "pity-{$i}", 'tires', 1)['ppm'];
            $sumWith += $with->attempt($held, 'dry-grip', "pity-{$i}", 'tires', 1)['ppm'];
        }

        // A sigma higher (23 000 ppm) on average, less what the cut at the most takes.
        expect(($sumWith - $sumWithout) / 600)->toBeGreaterThan(15_000);
    });

    it('has places as the model says, from none to three, and the number of the rules when it says nothing', function () {
        $draw = tuningDraw();

        expect($draw->slotsFor(null))->toBe(3)->and($draw->slotsFor(0))->toBe(0)->and($draw->slotsFor(2))->toBe(2)->and($draw->slotsFor(9))->toBe(3)->and($draw->slotsFor(-1))->toBe(0);
    });

    it('allows less at its best to a bonus with no context than to one with a context', function () {
        $draw = tuningDraw();

        expect($draw->limit([]))->toBe(80_000)->and($draw->limit(['context' => ['kinds' => ['sprint']]]))->toBe(120_000);
    });
});

describe('the tuning of the rules', function () {
    it('has the eleven bonuses, each with a name and a description in five languages, inside the limits', function () {
        $tuning = tuningRules();
        $draw = new Tuning($tuning);

        expect(array_keys($tuning['definitions']))->toBe(['sprint-launch', 'sprint-brake', 'endurance-economy', 'endurance-wear', 'endurance-reliability', 'dry-grip', 'gravel-grip', 'snow-grip', 'rain-handling', 'fog-speed', 'gravel-clearance']);

        foreach ($tuning['definitions'] as $key => $bonus) {
            expect(array_keys($bonus['name']))->toBe(Locale::supported(), $key)->and(array_keys($bonus['description']))->toBe(Locale::supported(), $key)
                ->and($bonus['min_ppm'])->toBeLessThanOrEqual($bonus['mean_ppm'])->and($bonus['mean_ppm'])->toBeLessThanOrEqual($bonus['max_ppm'], $key)
                ->and($bonus['max_ppm'])->toBeLessThanOrEqual($draw->limit($bonus), $key);
        }
    });

    it('is refused when a bonus gives more at its best than the rules allow, or the mean is outside its range', function () {
        $greedy = PackValidator::fromConfig()->validateSource(contentWith(['rules/starter' => function (array $rules): array {
            $rules['tuning']['definitions']['dry-grip']['max_ppm'] = 130_000;
            $rules['tuning']['definitions']['sprint-brake']['mean_ppm'] = 5_000;

            return $rules;
        }]));
        $plain = PackValidator::fromConfig()->validateSource(contentWith(['rules/starter' => function (array $rules): array {
            // A bonus with no context works on every track, so it may give less at its best.
            $rules['tuning']['definitions']['sprint-launch']['context'] = new stdClass;

            return $rules;
        }]));

        expect(array_column($greedy->errors(), 'pointer'))->toContain('/tuning/definitions/dry-grip/max_ppm', '/tuning/definitions/sprint-brake/mean_ppm')
            ->and($greedy->isValid())->toBeFalse()
            ->and(array_column($plain->errors(), 'pointer'))->toContain('/tuning/definitions/sprint-launch/max_ppm');
    });

    it('is refused when a bonus has the key of an affix, because both are read by their key', function () {
        $clash = PackValidator::fromConfig()->validateSource(contentWith(['rules/starter' => function (array $rules): array {
            $rules['tuning']['definitions']['grip'] = $rules['tuning']['definitions']['dry-grip'];

            return $rules;
        }]));

        expect(array_column($clash->errors(), 'pointer'))->toContain('/tuning/definitions/grip');
    });

    it('lets a part say how many places it has, from none to three, and refuses more', function () {
        $ok = PackValidator::fromConfig()->validateSource(contentWith(['parts/street-tires-t2' => function (array $part): array {
            $part['tuning_slots'] = 0;

            return $part;
        }]));
        $many = PackValidator::fromConfig()->validateSource(contentWith(['parts/street-tires-t2' => function (array $part): array {
            $part['tuning_slots'] = 4;

            return $part;
        }]));

        expect($ok->isValid())->toBeTrue()->and($many->isValid())->toBeFalse();
    });
});
