<?php

declare(strict_types=1);

use App\Content\Publishing\PackGates;
use App\Services\MissionBudget;

require_once __DIR__.'/../../Support/MissionHelpers.php';

// What the missions come to: one report that the command, the gate of the publication and the page of the panel all take their numbers from, and the limits that the gate
// holds the content to.

describe('the report', function () {
    it('has the numbers that the command prints for the Rdzak on the first track at level 12: a month is about three quarters of the cash of 30 tanks and under half of the XP', function () {
        $report = app(MissionBudget::class)->report('rdzak-gt-4', 'zlomowo-street', 12);

        expect($report['tank'])->toMatchArray(['laps' => 30, 'xp' => 600])
            ->and($report['periods']['daily'])->toMatchArray(['missions' => 6, 'coins' => 2, 'cash_ppm' => 100_000, 'xp_ppm' => 60_000])
            ->and($report['periods']['daily']['worth'])->toEqualWithDelta(3.6, 0.05)
            ->and($report['month']['cash_percent'])->toBe(73)
            ->and($report['month']['xp_percent'])->toBe(44)
            ->and((int) round($report['month']['coins']))->toBe(119)
            ->and($report['vip'])->toBe(['coins' => 150, 'month_percent' => 80])
            ->and($report['main'])->toMatchArray(['cash_ppm' => 150_000, 'xp_ppm' => 90_000, 'missions' => 32])
            ->and($report['main']['cash_cents'])->toBeGreaterThan(3_000_000)
            ->and(array_column($report['checks'], 'ok'))->toBe([true, true]);
    });

    it('has the shares for every car, and amounts only for a car that can drive the track', function () {
        $report = app(MissionBudget::class)->report('no-such-car', 'zlomowo-street', 12);

        expect($report['tank'])->toBeNull()
            ->and($report['month']['cash_share'])->toBeBetween(0.15, 1.0)
            ->and($report['month']['cash_cents'])->toBeNull()
            ->and($report['main']['cash_cents'])->toBeNull()
            ->and($report['periods']['daily']['templates'][0]['cash_cents'])->toBeNull()
            ->and($report['periods']['daily']['worth'])->toBeGreaterThan(0)
            ->and(array_column($report['checks'], 'ok'))->toBe([true, true]);
    });

    it('lists what every template asks, is worth and pays, and whether it fits the level', function () {
        $templates = app(MissionBudget::class)->report('rdzak-gt-4', 'zlomowo-street', 1)['periods']['daily']['templates'];
        $upgrade = collect($templates)->firstWhere('template', 'daily-upgrade');
        $laps = collect($templates)->firstWhere('template', 'daily-laps');

        // Upgrading is for level 10 and up; the laps are 60% of the 30 laps of a tank, worth that part of a tank of work.
        expect($upgrade['fits'])->toBeFalse()->and($upgrade['asks'])->toBeNull()
            ->and($laps)->toMatchArray(['fits' => true, 'asks' => 18, 'money' => false])
            ->and($laps['worth'])->toBeGreaterThan(0)
            ->and($laps['cash_cents'])->toBeGreaterThan(0)
            ->and($laps['xp'])->toBeGreaterThan(0);
    });

    it('follows what the rules pay of the worth: twice the part is twice the share', function () {
        $before = app(MissionBudget::class)->report('rdzak-gt-4', 'zlomowo-street', 12)['month']['cash_share'];

        missionRules(function (array $rules): array {
            foreach (['daily', 'weekly', 'monthly'] as $period) {
                $rules['missions']['periods'][$period]['cash_ppm'] *= 2;
            }

            return $rules;
        });

        expect(app(MissionBudget::class)->report('rdzak-gt-4', 'zlomowo-street', 12)['month']['cash_share'])->toEqualWithDelta(2 * $before, 0.0001);
    });

    it('is nothing for a game that has no missions', function () {
        missionRules(function (array $rules): array {
            unset($rules['missions']);

            return $rules;
        });

        expect(app(MissionBudget::class)->report('rdzak-gt-4', 'zlomowo-street'))->toBeNull();
    });
});

describe('the gate of the publication', function () {
    it('is one of the gates that a pack goes through', function () {
        expect(array_keys((new ReflectionClassConstant(PackGates::class, 'GATES'))->getValue()))->toContain('missions:budget --strict');
    });

    it('lets the content that ships through, and says where every number stands', function () {
        $this->artisan('missions:budget', ['--strict' => true])
            ->expectsOutputToContain('ok   cash_share')
            ->expectsOutputToContain('ok   xp_share')
            ->assertSuccessful();
    });

    it('stops a month of sets that pays more cash than the limit lets it', function () {
        missionRules(function (array $rules): array {
            $rules['missions']['periods']['daily']['cash_ppm'] = 1_000_000;

            return $rules;
        });

        $this->artisan('missions:budget', ['--strict' => true])->expectsOutputToContain('FAIL cash_share')->assertFailed();
    });

    it('stops a month of sets that pays too little XP, and says so without --strict only by the lines', function () {
        missionRules(function (array $rules): array {
            foreach (['daily', 'weekly', 'monthly'] as $period) {
                $rules['missions']['periods'][$period]['xp_ppm'] = 1_000;
            }

            return $rules;
        });

        $this->artisan('missions:budget', ['--strict' => true])->expectsOutputToContain('FAIL xp_share')->assertFailed();
        $this->artisan('missions:budget')->expectsOutputToContain('FAIL xp_share')->assertSuccessful();
    });

    it('judges the level that the limits are for, and no other, unless it is asked for another', function () {
        // Below level 10 the upgrades do not fit, so the month is worth less: the same content is further from the lower limit there.
        $strict = app(MissionBudget::class)->report('rdzak-gt-4', 'zlomowo-street', (int) config('ride.missions.budget.level'))['month']['cash_share'];
        $beginner = app(MissionBudget::class)->report('rdzak-gt-4', 'zlomowo-street', 1)['month']['cash_share'];

        expect(config('ride.missions.budget.level'))->toBe(12)->and($beginner)->toBeLessThan($strict);
    });

    it('lets a game with no reference car or no missions through: it judges the shares, and there is nothing to judge', function () {
        $this->artisan('missions:budget', ['--strict' => true, '--car' => 'no-such-car'])->assertSuccessful();

        missionRules(function (array $rules): array {
            unset($rules['missions']);

            return $rules;
        });

        $this->artisan('missions:budget', ['--strict' => true])->assertSuccessful();
        $this->artisan('missions:budget')->assertFailed();
    });

    it('gives the panel the same numbers as JSON, and fails with the gate the same way', function () {
        $this->artisan('missions:budget', ['--json' => true])->assertSuccessful();

        missionRules(function (array $rules): array {
            $rules['missions']['periods']['monthly']['cash_ppm'] = 5_000_000;

            return $rules;
        });

        $this->artisan('missions:budget', ['--json' => true, '--strict' => true])->assertFailed();
    });
});
