<?php

declare(strict_types=1);

use App\Content\PackValidator;
use App\Models\CrateOpening;
use App\Models\LedgerEntry;
use App\Models\StreakDay;
use App\Models\TutorialProgress;
use App\Models\User;
use App\Models\UserStreak;
use App\Services\AccountErasure;
use App\Services\AccountExport;
use App\Services\Streaks;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../../Support/MissionHelpers.php';

// The streak of days: counted out of the daily sets (the goal of a day is a number of the missions done), saved by a shield, paid once, and taken by the server when the
// day is over. The sets are the small pools of the tests (a few laps and a service), and the days are the days of the missions, from four in the morning.

afterEach(fn () => Carbon::setTestNow());

/** The rules with a streak that asks for one mission of the day, earns a shield every two days (one at most) and pays a cash and a crate on the third day. */
function theStreakIs(array $change = []): void
{
    missionRules(fn (array $data): array => [...$data, 'streaks' => [
        'enabled' => true,
        'goal' => ['daily_done' => 1],
        'shield' => ['every_days' => 2, 'cap' => 1],
        'cycle' => [
            ['cash_ppm' => 20_000, 'xp_ppm' => 0],
            ['cash_ppm' => 30_000, 'xp_ppm' => 10_000],
            ['cash_ppm' => 40_000, 'xp_ppm' => 0, 'items' => [['type' => 'crate', 'kind' => 'slot']]],
        ],
        'milestones' => [['day' => 4, 'cash_ppm' => 100_000]],
        ...$change,
    ]]);
}

beforeEach(function () {
    theMissionsAre();
    theStreakIs();
});

/** The driver does the goal of the day it is: the service mission is done by a job the mechanic finished now. */
function doTheGoalOfTheDay(User $driver): void
{
    missionsOf($driver)->assertOk();
    doneJob($driver);
    missionsOf($driver)->assertOk();
}

function streakOf(User $driver): ?UserStreak
{
    return UserStreak::query()->find($driver->id);
}

function nextDay(int $days = 1): void
{
    Carbon::setTestNow(Carbon::now()->addDays($days));
}

describe('the days that count', function () {
    it('counts a day when enough missions of the set are done, and pays nothing until it is taken', function () {
        $driver = missionDriver();
        $cash = cashOfDriver($driver);

        missionsOf($driver)->assertOk()->assertJsonPath('data.streak.current', 0)->assertJsonPath('data.streak.goal.needed', 1)->assertJsonPath('data.streak.goal.reached', false);

        doTheGoalOfTheDay($driver);

        missionsOf($driver)->assertJsonPath('data.streak.current', 1)->assertJsonPath('data.streak.goal.reached', true)->assertJsonPath('data.streak.today.state', 'ready')
            ->assertJsonPath('data.streak.today.cycle_day', 1);
        expect(StreakDay::query()->where('user_id', $driver->id)->count())->toBe(1)
            // The day is taken like the mission of it, so the number next to the missions counts it.
            ->and(cashOfDriver($driver))->toBe($cash);
    });

    it('counts a day once, however often somebody looks', function () {
        $driver = missionDriver();

        doTheGoalOfTheDay($driver);
        missionsOf($driver);
        missionsOf($driver);

        expect(streakOf($driver)->current)->toBe(1)->and(StreakDay::query()->count())->toBe(1);
    });

    it('does not count a day on which the goal was not done', function () {
        $driver = missionDriver();

        missionsOf($driver)->assertOk();

        expect(streakOf($driver)?->current ?? 0)->toBe(0)->and(StreakDay::query()->count())->toBe(0);
    });

    it('asks for all the missions of a set that is shorter than the goal', function () {
        theStreakIs(['goal' => ['daily_done' => 5]]);
        $driver = missionDriver();

        missionsOf($driver)->assertJsonPath('data.streak.goal.needed', 2);
        doneJob($driver);

        // The set has two missions and one is done: not enough yet.
        missionsOf($driver)->assertJsonPath('data.streak.goal.reached', false)->assertJsonPath('data.streak.goal.done', 1);
    });

    it('is not there before the missions are', function () {
        $early = missionDriver(started: false);
        TutorialProgress::query()->where('user_id', $early->id)->delete();

        missionsOf($early)->assertOk()->assertJsonPath('data.streak', null);
    });

    it('is not there when the rules switch it off', function () {
        theStreakIs(['enabled' => false]);
        $driver = missionDriver();

        missionsOf($driver)->assertOk()->assertJsonPath('data.streak', null);
        $this->postJson('/api/v1/missions/streak/claim', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'streak_unavailable');
    });
});

describe('the days in a row', function () {
    it('makes the streak a day longer every day the goal is done, and goes round the cycle', function () {
        $driver = missionDriver();

        foreach ([1, 2, 3, 4] as $day) {
            doTheGoalOfTheDay($driver);
            nextDay();
            missionsOf($driver);
            $row = streakOf($driver);

            expect($row->current)->toBe($day)->and($row->best)->toBe($day);
        }

        // Four days on a cycle of three: the fourth pays the first entry of it, and its milestone.
        expect(streakOf($driver)->cycle_day)->toBe(1)
            ->and(StreakDay::query()->where('user_id', $driver->id)->where('streak', 4)->first()->cycle_day)->toBe(1)
            ->and(StreakDay::query()->where('user_id', $driver->id)->where('streak', 4)->first()->reward['cash_cents'])->toBeGreaterThan(StreakDay::query()->where('user_id', $driver->id)->where('streak', 1)->first()->reward['cash_cents']);
    });

    it('earns a shield when the streak reaches the days of the rules, and keeps at most the cap', function () {
        $driver = missionDriver();

        doTheGoalOfTheDay($driver);
        expect(streakOf($driver)->shields)->toBe(0);

        nextDay();
        doTheGoalOfTheDay($driver);
        expect(streakOf($driver)->shields)->toBe(1);

        nextDay();
        doTheGoalOfTheDay($driver);
        nextDay();
        doTheGoalOfTheDay($driver);

        // The streak is four days and would have earned a second shield: the cap is one.
        expect(streakOf($driver)->current)->toBe(4)->and(streakOf($driver)->shields)->toBe(1);
    });

    it('saves a day that was missed with a shield: the streak stands and the day pays nothing', function () {
        $driver = missionDriver();

        doTheGoalOfTheDay($driver);
        nextDay();
        doTheGoalOfTheDay($driver);
        // Two days make a shield. A day is missed, and the driver comes back on the next one.
        nextDay(2);
        doTheGoalOfTheDay($driver);

        $row = streakOf($driver);
        $saved = StreakDay::query()->where('user_id', $driver->id)->where('kind', StreakDay::KIND_SHIELD)->first();

        expect($row->current)->toBe(3)->and($row->shields)->toBe(0)->and($saved)->not->toBeNull()->and($saved->streak)->toBe(2)->and($saved->reward)->toBeNull();
    });

    it('ends the streak on a day that was missed when there is no shield, and keeps the best', function () {
        $driver = missionDriver();

        doTheGoalOfTheDay($driver);
        nextDay(2);
        missionsOf($driver)->assertOk()->assertJsonPath('data.streak.current', 0)->assertJsonPath('data.streak.best', 1)->assertJsonPath('data.streak.lost.streak', 1);

        // The next day that counts starts a streak again, from the first entry of the cycle.
        doTheGoalOfTheDay($driver);
        missionsOf($driver)->assertJsonPath('data.streak.current', 1)->assertJsonPath('data.streak.cycle_day', 1)->assertJsonPath('data.streak.lost', null);
    });

    it('does not walk a long absence day by day, and ends the streak', function () {
        $driver = missionDriver();

        doTheGoalOfTheDay($driver);
        nextDay(120);
        missionsOf($driver)->assertOk()->assertJsonPath('data.streak.current', 0);

        expect(streakOf($driver)->last_day)->toBe(Carbon::now()->subDay()->setTimezone('Europe/Warsaw')->format('Y-m-d'));
    });

    it('counts the laps of a day that were driven while the driver was away in the day they belong to', function () {
        $driver = missionDriver();

        // The driver looks in the evening and does not do the goal; the job is finished in the same day, but nobody looks until the day after.
        missionsOf($driver)->assertOk();
        doneJob($driver);
        nextDay();
        missionsOf($driver)->assertOk()->assertJsonPath('data.streak.current', 1);
    });
});

describe('what a day pays', function () {
    it('is taken by the driver once, through the ledger, and again takes nothing', function () {
        $driver = missionDriver();
        doTheGoalOfTheDay($driver);
        $cash = cashOfDriver($driver);
        $pays = StreakDay::query()->where('user_id', $driver->id)->first()->reward['cash_cents'];

        $this->postJson('/api/v1/missions/streak/claim', [], bearerFor($driver))->assertOk()->assertJsonPath('repeated', false)->assertJsonPath('claimed.cash_cents', $pays)
            ->assertJsonPath('data.streak.today.state', 'claimed');
        $this->postJson('/api/v1/missions/streak/claim', [], bearerFor($driver))->assertOk()->assertJsonPath('repeated', true)->assertJsonPath('claimed.cash_cents', 0);

        expect($pays)->toBeGreaterThan(0)
            ->and(cashOfDriver($driver))->toBe($cash + $pays)
            ->and(LedgerEntry::query()->where('user_id', $driver->id)->where('reason', 'streak')->count())->toBe(1)
            ->and(LedgerEntry::query()->where('idem_key', 'like', 'streak:%')->first()->idem_key)->toBe('streak:'.$driver->id.':'.StreakDay::query()->first()->day_key);
    });

    it('is taken by the server when the day is over and the driver did not take it', function () {
        $driver = missionDriver();
        doTheGoalOfTheDay($driver);
        $cash = cashOfDriver($driver);
        $pays = StreakDay::query()->where('user_id', $driver->id)->first()->reward['cash_cents'];

        nextDay();
        missionsOf($driver)->assertOk();

        $day = StreakDay::query()->where('user_id', $driver->id)->where('streak', 1)->first();

        expect($day->auto_claimed)->toBeTrue()->and($day->claimed_at)->not->toBeNull()->and(cashOfDriver($driver))->toBeGreaterThanOrEqual($cash + $pays);
    });

    it('counts the days to take in the number next to the missions, and opens the crate of the third day', function () {
        $driver = missionDriver();

        doTheGoalOfTheDay($driver);
        nextDay();
        doTheGoalOfTheDay($driver);
        nextDay();
        doTheGoalOfTheDay($driver);

        // The first two days were taken by the server; the third waits for the driver, and its crate is not opened until they take it.
        missionsOf($driver)->assertJsonPath('data.streak.today.state', 'ready');
        $crates = CrateOpening::query()->where('user_id', $driver->id)->count();
        $this->postJson('/api/v1/missions/streak/claim', [], bearerFor($driver))->assertOk()->assertJsonPath('claimed.crates.0', 'slot');

        expect(CrateOpening::query()->where('user_id', $driver->id)->count())->toBe($crates + 1);
    });

    it('pays no premium coins', function () {
        $driver = missionDriver();
        $coins = coinsOfDriver($driver);

        doTheGoalOfTheDay($driver);
        $this->postJson('/api/v1/missions/streak/claim', [], bearerFor($driver))->assertOk();

        expect(coinsOfDriver($driver))->toBe($coins);
    });
});

describe('the tools', function () {
    it('shows the budget of the streak next to what the cars earn, and stays inside the limits for the rules that ship', function () {
        missionRules(fn (array $data): array => $data);

        $this->artisan('streaks:budget')
            ->expectsOutputToContain('A cycle of 7 days pays')
            ->expectsOutputToContain('The streak pays no premium coins.')
            ->assertSuccessful();
    });

    it('warns when the streak pays more than a gift outside the economy should', function () {
        missionRules(fn (array $data): array => [...$data, 'streaks' => [...$data['streaks'], 'cycle' => [['cash_ppm' => 900_000, 'xp_ppm' => 0]]]]);

        $this->artisan('streaks:budget')->expectsOutputToContain('MORE THAN 10%')->assertFailed();
    });

    it('refuses a milestone named twice, and warns when the missions it is counted from are off', function () {
        $content = contentWith(['rules/starter' => function (array $rules): array {
            $rules['streaks']['milestones'] = [['day' => 14], ['day' => 14]];
            $rules['missions']['enabled'] = false;

            return $rules;
        }]);
        $result = PackValidator::fromConfig()->validateSource($content);
        $errors = array_map(static fn (array $issue): string => "{$issue['file']} {$issue['pointer']}", $result->errors());
        $warnings = array_map(static fn (array $issue): string => "{$issue['file']} {$issue['pointer']}", $result->warnings());

        expect($errors)->toContain('rules/starter.json /streaks/milestones/1/day')->and($warnings)->toContain('rules/starter.json /streaks/enabled');
    });

    it('is in the copy of the data of the account, and is removed with the account', function () {
        $driver = missionDriver();
        doTheGoalOfTheDay($driver);

        $game = app(AccountExport::class)->for($driver)['game'];

        expect($game['streak']['current'])->toBe(1)->and($game['streak_days'])->toHaveCount(1)->and($game['streak_days'][0])->not->toHaveKey('user_id');

        $removed = app(AccountErasure::class)->erase($driver);

        expect($removed['streak days'])->toBe(1)->and(StreakDay::query()->where('user_id', $driver->id)->count())->toBe(0)->and(UserStreak::query()->find($driver->id))->toBeNull();
    });
});

describe('the summary of the break', function () {
    it('tells that the streak was kept by a shield and what the server took', function () {
        $driver = missionDriver();
        doTheGoalOfTheDay($driver);
        nextDay();
        doTheGoalOfTheDay($driver);
        $from = Carbon::now()->addMinute();
        nextDay(2);
        doTheGoalOfTheDay($driver);

        $report = app(Streaks::class)->awayReport($driver->id, $from);

        expect($report['shields_used'])->toBe(1)->and($report['counted'])->toBe(1)->and($report['lost'])->toBeNull()->and($report['current'])->toBe(3);
    });
});
