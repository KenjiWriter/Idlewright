<?php

declare(strict_types=1);

use App\Domain\Economy\CrateEconomy;
use App\Domain\Economy\DayEffect;
use App\Domain\Economy\DayState;
use App\Domain\Economy\EconomyRules;
use App\Domain\Economy\EffortAudit;
use App\Domain\Economy\Extra;
use App\Domain\Economy\MissionEconomy;
use App\Domain\Economy\MissionsExtra;
use App\Domain\Economy\PassExtra;
use App\Domain\Economy\PayerStudy;
use App\Domain\Economy\PerkEconomy;
use App\Domain\Economy\RefillExtra;
use App\Domain\Economy\Simulator;
use App\Domain\Economy\VipExtra;

/** A template that every level can draw: its weight and its worth in tanks. */
function aMissionTemplate(int $weight, float $worth, int $min = 1, int $max = PHP_INT_MAX): array
{
    return ['weight' => $weight, 'worth' => $worth, 'min_level' => $min, 'max_level' => $max];
}

/**
 * Missions made of numbers: a day of two slots (the second opens at the fifth level), a week of one and a month of one, each paying a tenth of its worth in cash and a
 * twentieth in experience.
 */
function aMissionEconomy(): MissionEconomy
{
    return new MissionEconomy([
        'daily' => ['cash_ppm' => 100_000, 'xp_ppm' => 50_000, 'bonus_coins' => 2, 'slots' => [
            [aMissionTemplate(3, 0.6), aMissionTemplate(1, 1.0)],
            [aMissionTemplate(1, 0.3, 5)],
        ]],
        'weekly' => ['cash_ppm' => 100_000, 'xp_ppm' => 50_000, 'bonus_coins' => 8, 'slots' => [[aMissionTemplate(1, 7.0)]]],
        'monthly' => ['cash_ppm' => 100_000, 'xp_ppm' => 50_000, 'bonus_coins' => 25, 'slots' => [[aMissionTemplate(1, 30.0)]]],
    ]);
}

/** The paid extras of the game as the starter rules give them, with a coin at the price of the smallest pack (4.99 for 50 coins). */
function somePerks(array $with = []): PerkEconomy
{
    return new PerkEconomy(...[
        'vipCashPpm' => 200_000,
        'vipXpPpm' => 200_000,
        'vipCoins' => 150,
        'vipDays' => 30,
        'passPriceMinor' => 1999,
        'passDays' => 30,
        'coinsPerCharge' => 1.6,
        'breakChancePerLapPpm' => 3000,
        'minorPerCoin' => 499 / 50,
        ...$with,
    ]);
}

/** A day of a player at a rung: the income and the experience that the driving gave, and the income of the wrecks. */
function aPerkDay(string $player = 'B', int $rung = 1, int $level = 5, float $income = 1_000.0, float $xpToday = 900.0, float $other = 0.0, int $day = 1): DayState
{
    return new DayState(economyRules(), economyRules()->players()[$player], $day, $rung, $level, $income, 100_000.0, $xpToday, $other);
}

describe('the missions of a game', function () {
    it('are worth, at a level, the mean of every slot over the templates that fit it, by their weights', function () {
        $missions = aMissionEconomy();

        // Slot one: (3 * 0.6 + 1 * 1.0) / 4 = 0.7; slot two asks the fifth level.
        expect($missions->worthAt('daily', 1))->toEqualWithDelta(0.7, 1e-9)
            ->and($missions->worthAt('daily', 5))->toEqualWithDelta(1.0, 1e-9)
            ->and($missions->worthAt('weekly', 1))->toEqualWithDelta(7.0, 1e-9)
            ->and($missions->missionsIn('daily'))->toBe(2);
    });

    it('pay a day, on average, a part of what the sets of the day, the week and the month are worth: a day, a seventh and a thirtieth of them', function () {
        $tanks = aMissionEconomy()->tanksPerDay(5);

        // Worth 1.0 a day, 7.0 a week and 30.0 a month: one tank a day in all, a tenth of it in cash and a twentieth in experience.
        expect($tanks['cash'])->toEqualWithDelta(0.3, 1e-9)->and($tanks['xp'])->toEqualWithDelta(0.15, 1e-9)
            ->and(aMissionEconomy()->tanksPerDay(5, 0.5)['cash'])->toEqualWithDelta(0.15, 1e-9);
    });

    it('pay the coins of a set to a player who completes all of it: the chance of that is the share of each mission to the power of the missions', function () {
        $missions = aMissionEconomy();

        expect($missions->coinsPerDay(1.0))->toEqualWithDelta(2 + 8 / 7 + 25 / 30, 1e-9)
            ->and($missions->coinsPerDay(0.5))->toEqualWithDelta(2 * 0.25 + 8 * 0.5 / 7 + 25 * 0.5 / 30, 1e-9);
    });

    it('pay a player who does all of them the cash and the experience of the tank of its car, and one who does less pays less in proportion', function () {
        $rules = economyRules();
        $day = aPerkDay(rung: 2, level: 5);
        $tanks = aMissionEconomy()->tanksPerDay(5);
        $tank = $rules->netPerHour(2) * $rules->offlineWindowHours(2);

        $all = (new MissionsExtra(aMissionEconomy(), 1.0))->day($day);
        $most = (new MissionsExtra(aMissionEconomy(), 0.6))->day($day);

        expect($all->cash)->toEqualWithDelta($tanks['cash'] * $tank, 1e-6)
            ->and($all->xp)->toEqualWithDelta($tanks['xp'] * $rules->xpPerHourOfDriving() * $rules->offlineWindowHours(2), 1e-6)
            ->and($most->cash)->toEqualWithDelta(0.6 * $all->cash, 1e-6)
            ->and($all->incomeFactor)->toBe(1.0);
    });

    it('say what they added to the first thirty days and what coins they brought', function () {
        $extra = new MissionsExtra(aMissionEconomy(), 1.0);

        foreach (range(1, 40) as $day) {
            $extra->day(aPerkDay(income: 1_000.0, xpToday: 500.0, day: $day));
        }

        $report = $extra->report();

        expect($report['days'])->toBe(40)
            ->and($report['uplift_cash_30d'])->toBeGreaterThan(0.0)
            ->and($report['coins'])->toEqualWithDelta(40 * (2 + 8 / 7 + 25 / 30), 0.01)
            ->and($report['coins_per_30d'])->toEqualWithDelta(30 * (2 + 8 / 7 + 25 / 30), 0.01);
    });
});

describe('the VIP account', function () {
    it('pays the bonus on what the laps pay before the fuel, for the main car and the wrecks, and a fifth more experience', function () {
        $rules = economyRules();
        $effect = (new VipExtra(somePerks()))->day(aPerkDay(rung: 3, income: 1_000.0, other: 400.0));

        $gross = fn (int $rung): float => $rules->grossPerHour($rung) / $rules->netPerHour($rung);

        expect($effect->cash)->toEqualWithDelta(0.2 * (1_000.0 * $gross(3) + 400.0 * $gross(0)), 1e-6)
            ->and($effect->xpFactor)->toEqualWithDelta(1.2, 1e-9)
            ->and($effect->incomeFactor)->toBe(1.0);
    });

    it('costs the coins of the account for a month, at the price of the smallest pack', function () {
        $report = (new VipExtra(somePerks()))->report();

        expect($report['coins_per_30d'])->toBe(150.0)->and($report['price_per_30d'])->toEqualWithDelta(14.97, 0.001)->and($report['currency'])->toBe('PLN');
    });
});

describe('the auto-refill and the pass', function () {
    it('add the hours of the car that drives alone, at what an hour pays before the fuel, less the upkeep, and the experience of those hours', function () {
        $rules = economyRules();
        // Player B comes once a day: one tank of 1 h at the first rung, and 7.4 h between two breakdowns at 45 laps an hour.
        $effect = (new RefillExtra(somePerks(), 3.0))->day(aPerkDay(player: 'B', rung: 1));

        expect($effect->cash)->toEqualWithDelta(3.0 * ($rules->grossPerHour(1) - 0.1 * $rules->netPerHour(1)), 1e-6)
            ->and($effect->xp)->toEqualWithDelta(3.0 * 900.0, 1e-6);
    });

    it('do not drive the car past what it drives between two breakdowns, however many charges are used', function () {
        $rules = economyRules();
        $between = 1_000_000 / (3000 * 45);
        $effect = (new RefillExtra(somePerks(), 50.0))->day(aPerkDay(player: 'B', rung: 1));

        expect($effect->xp)->toEqualWithDelta(($between - 1.0) * 900.0, 1e-6)
            ->and($effect->cash)->toEqualWithDelta(($between - 1.0) * ($rules->grossPerHour(1) - 0.1 * $rules->netPerHour(1)), 1e-6);
    });

    it('make the pass drive the car up to the next breakdown, with the fuel of the hours that the player pays for free as well', function () {
        $rules = economyRules();
        $between = 1_000_000 / (3000 * 45);
        $effect = (new PassExtra(somePerks()))->day(aPerkDay(player: 'B', rung: 1));

        $expected = ($between - 1.0) * ($rules->grossPerHour(1) - 0.1 * $rules->netPerHour(1)) + 1.0 * $rules->fuelPerHour(1) * 1.0;

        expect($effect->cash)->toEqualWithDelta($expected, 1e-6)->and($effect->xp)->toEqualWithDelta(($between - 1.0) * 900.0, 1e-6);
    });

    it('drive a car that the habits of the player stop at its hours up to the day, and use no charge when none is asked for', function () {
        // The intensive player at the last rung drives at most 18 h a day (12 h of window and 8 visits); the car goes 13.9 h between breakdowns, 8 times over: the whole day.
        $effect = (new PassExtra(somePerks()))->day(aPerkDay(player: 'C', rung: 8));

        expect($effect->xp)->toEqualWithDelta((24.0 - 18.0) * 900.0, 1e-6);

        $none = (new RefillExtra(somePerks(), 0.0))->day(aPerkDay(player: 'C', rung: 8));

        expect($none->cash)->toBe(0.0)->and($none->xp)->toBe(0.0);
    });

    it('cost the price of the pass for a month, and the coins of the charges used at the price of the best pack of charges', function () {
        $rules = economyRules();
        $refill = new RefillExtra(somePerks(), 1.0);

        foreach (range(1, 30) as $day) {
            $refill->day(aPerkDay(player: 'B', rung: 1, day: $day));
        }

        // One charge a day for thirty days: 30 charges at 1.6 coins, at 9.98 grosze a coin.
        expect($refill->report()['coins_per_30d'])->toEqualWithDelta(48.0, 1e-6)
            ->and($refill->report()['price_per_30d'])->toEqualWithDelta(4.79, 0.01)
            ->and((new PassExtra(somePerks()))->report()['price_per_30d'])->toEqualWithDelta(19.99, 1e-9)
            ->and($rules->upkeepShare())->toBe(0.1);
    });
});

describe('the experience that an extra gives', function () {
    it('is added to the experience of the day and reaches the level that the purchases of the day are asked for', function () {
        $boost = new class implements Extra
        {
            public function name(): string
            {
                return 'boost';
            }

            public function day(DayState $state): DayEffect
            {
                return new DayEffect(xp: $state->day === 1 ? 1_000_000_000.0 : 0.0);
            }

            public function stepCostDelta(EconomyRules $rules, int $rung): float
            {
                return 0.0;
            }

            public function report(): array
            {
                return [];
            }
        };

        $rules = economyRules();
        $simulator = new Simulator;
        $player = $rules->players()['B'];
        $plain = $simulator->simulateGame($rules, $player);
        $boosted = $simulator->simulateGame($rules, $player, [$boost]);

        // The plain player waits for the level of a car at least once, and the boosted one never does: it buys on the day that the cash is there.
        $waited = static fn ($run): array => array_filter($run->purchases, static fn ($purchase): bool => $purchase->cashReadyDay < $purchase->day);

        expect($waited($plain))->not->toBe([])
            ->and($waited($boosted))->toBe([])
            ->and($boosted->xp)->toBeGreaterThan(1_000_000_000.0)
            ->and($boosted->dayOf(8))->toBeLessThanOrEqual($plain->dayOf(8));
    });

    it('is told to the extras as the experience that the driving of the day gave', function () {
        $seen = [];
        $spy = new class($seen) implements Extra
        {
            public function __construct(public array &$seen) {}

            public function name(): string
            {
                return 'spy';
            }

            public function day(DayState $state): DayEffect
            {
                $this->seen[] = [$state->xpToday, $state->otherIncome];

                return DayEffect::none();
            }

            public function stepCostDelta(EconomyRules $rules, int $rung): float
            {
                return 0.0;
            }

            public function report(): array
            {
                return [];
            }
        };

        $rules = economyRules();
        (new Simulator)->simulateGame($rules, $rules->players()['A'], [$spy]);

        // The first day: 3 visits of a window of half an hour at the first rung, an hour and a half at 1125 experience an hour (900 times the activity of 1.25).
        expect($seen[0][0])->toEqualWithDelta(1.5 * 900.0 * 1.25, 1e-6)->and($seen[0][1])->toBe(0.0);
    });
});

describe('the payers study', function () {
    it('plays every extra that the game has against the player who pays nothing, and the days of the one who pays nothing are the days of the scenario', function () {
        $rules = economyRules();
        $study = (new PayerStudy(new Simulator))->run($rules, aMissionEconomy(), somePerks());
        $scenario = (new Simulator)->simulateAllGame($rules);

        expect(array_keys($study->variants))->toBe(['missions-100', 'missions-60', 'vip', 'refill-1', 'refill-3', 'pass', 'all-in'])
            ->and($study->dayOf(null, 'A', 8))->toBe($scenario['A']->dayOf(8))
            ->and($study->dayOf(null, 'B', 3))->toBe($scenario['B']->dayOf(3));
    });

    it('says that every payer is sooner than the player who pays nothing, the more the more it pays, and never later', function () {
        $study = (new PayerStudy(new Simulator))->run(economyRules(), aMissionEconomy(), somePerks(), ['B']);

        expect($study->saved('vip', 'B', 8))->toBeGreaterThan(0.0)
            ->and($study->saved('pass', 'B', 8))->toBeGreaterThan($study->saved('refill-1', 'B', 8))
            ->and($study->saved('missions-100', 'B', 8))->toBeGreaterThan($study->saved('missions-60', 'B', 8))
            ->and($study->saved('all-in', 'B', 8))->toBeGreaterThan($study->saved('pass', 'B', 8))
            ->and(array_keys($study->baseline))->toBe(['B']);
    });

    it('lists the places where a payer is ahead of the pace by more than the tolerance of the gate, as information', function () {
        $study = (new PayerStudy(new Simulator))->run(economyRules(), aMissionEconomy(), somePerks(), ['B']);
        $beyond = $study->beyondTolerance();

        expect(array_unique(array_column($beyond, 'variant')))->toContain('pass')
            ->and(array_unique(array_column($beyond, 'player')))->toBe(['B'])
            ->and(min(array_column($beyond, 'saved')))->toBeGreaterThan($study->tolerance);
    });

    it('has no row for what a game does not have: a game without missions has no missions, and one that sells nothing has no payers', function () {
        $rules = economyRules();
        $noMissions = (new PayerStudy(new Simulator))->run($rules, null, somePerks());
        $nothing = (new PayerStudy(new Simulator))->run($rules, null, null);
        $onlyMissions = (new PayerStudy(new Simulator))->run($rules, aMissionEconomy(), null);

        expect(array_keys($noMissions->variants))->toBe(['vip', 'refill-1', 'refill-3', 'pass', 'all-in'])
            ->and($nothing->variants)->toBe([])
            ->and(array_keys($onlyMissions->variants))->toBe(['missions-100', 'missions-60']);
    });
});

describe('what the missions pay at other levels, and the audit of their effort', function () {
    it('pay a part of the cash and the experience when they are scaled, and leave the coins, and say what a goal is worth', function () {
        $missions = (new MissionEconomy(aMissionEconomy()->periods, ['crate' => 1_000_000, 'car_bought' => 5_000_000]))->scaled(0.5);

        expect($missions->periods['daily']['cash_ppm'])->toBe(50_000)
            ->and($missions->periods['daily']['xp_ppm'])->toBe(25_000)
            ->and($missions->periods['daily']['bonus_coins'])->toBe(2)
            ->and($missions->tanksPerDay(5)['cash'])->toEqualWithDelta(0.15, 1e-9)
            ->and($missions->effortOf('car_bought'))->toBe(5.0)
            ->and($missions->effortOf('unheard-of'))->toBeNull();
    });

    it('play a player who does all of them at a quarter, a half and three quarters of what they pay, and the more they pay the sooner the cars come', function () {
        $study = (new PayerStudy(new Simulator))->run(economyRules(), aMissionEconomy(), somePerks(), ['B']);
        $levels = $study->missionLevels;
        $day = fn (string $key): int => $levels[$key]['B']->dayOf(8);

        expect(array_keys($levels))->toBe(['0.25', '0.5', '0.75'])
            ->and($day('0.25'))->toBeGreaterThanOrEqual($day('0.5'))
            ->and($day('0.5'))->toBeGreaterThanOrEqual($day('0.75'))
            ->and($day('0.75'))->toBeGreaterThanOrEqual($study->variants['missions-100']['runs']['B']->dayOf(8))
            ->and((new PayerStudy(new Simulator))->run(economyRules(), null, somePerks(), ['B'])->missionLevels)->toBe([]);
    });

    it('price a crate, a part and a car in tanks of the car of each rung, and say which goals cost more than twice or less than half of what the missions value them at', function () {
        $rules = economyRules();
        $crates = new CrateEconomy(1, 3, 1_000_000, ['tires' => 2, 'suspension' => 1, 'brakes' => 1], ['common' => 620, 'uncommon' => 250, 'rare' => 100, 'epic' => 27, 'legendary' => 3], 10, 'rare', 150, [0.0, 0.10, 0.15, 0.18, 0.20]);
        $missions = new MissionEconomy([], ['crate' => 1_000_000, 'parts_bought' => 1_000_000, 'car_bought' => 5_000_000]);

        $audit = new EffortAudit;
        $result = $audit->run($rules, $crates, $missions);
        $tank = $rules->netPerHour(1) * $rules->offlineWindowHours(1);
        $second = $result['rows'][1];

        expect($result['efforts'])->toBe(['crate' => 1.0, 'part' => 1.0, 'car' => 5.0])
            ->and($result['rows'])->toHaveCount($rules->rungs())
            ->and($second['level'])->toBe(3)
            ->and($second['tank'])->toEqualWithDelta($tank, 1e-6)
            ->and($second['crate'])->toEqualWithDelta($crates->cost($rules, 2) / $tank, 1e-9)
            ->and($second['car'])->toEqualWithDelta($rules->stepCost(1) / $tank, 1e-9)
            ->and(end($result['rows'])['car'])->toBeNull();

        // A car costs from 10 to 30 tanks and is said to be worth 5: off. A goal that the missions do not value is not audited.
        $off = $audit->off($result);

        expect($off)->toHaveKey('car')
            ->and($off['car']['effort'])->toBe(5.0)
            ->and($off['car']['highest'])->toBeGreaterThan(EffortAudit::TOLERANCE)
            ->and($audit->off($audit->run($rules, $crates, new MissionEconomy([], []))))->toBe([]);
    });
});
