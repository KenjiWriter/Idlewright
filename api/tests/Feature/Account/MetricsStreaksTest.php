<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserStreak;
use App\Models\UserVisit;
use App\Services\Metrics;
use Carbon\CarbonImmutable;

// The numbers of the streak of days next to the numbers of the alpha: who has a streak, whether they come back, and how many streaks are lost.

function streakReport(): array
{
    return app(Metrics::class)->report(CarbonImmutable::now()->subDays(60), CarbonImmutable::now()->addSecond(), CarbonImmutable::now())['streaks'];
}

/** An account made `$daysAgo` days ago, with a streak row and a visit `$visitDaysAfter` days after it was made (none when null). */
function streakAccount(int $daysAgo, int $current, int $best, ?int $visitDaysAfter, ?string $lostDay = null): User
{
    $user = User::factory()->guest()->create(['created_at' => now()->subDays($daysAgo)]);
    UserStreak::query()->create(['user_id' => $user->id, 'current' => $current, 'best' => $best, 'cycle_day' => 0, 'shields' => 0, 'lost' => $lostDay === null ? 0 : 5, 'lost_day' => $lostDay]);

    if ($visitDaysAfter !== null) {
        UserVisit::factory()->create(['user_id' => $user->id, 'at' => CarbonImmutable::instance($user->created_at)->startOfDay()->addDays($visitDaysAfter)->addHours(12)]);
    }

    return $user;
}

it('counts the drivers who came in the last seven days that have a streak of three and of seven days', function () {
    $short = streakAccount(20, 2, 2, 19);
    $three = streakAccount(20, 3, 3, 19);
    $seven = streakAccount(20, 8, 8, 19);
    streakAccount(20, 9, 9, null);

    $report = streakReport();

    expect($report['active'])->toBe(3)
        ->and($report['streak_3'])->toBe(['accounts' => 2, 'share' => 0.6667])
        ->and($report['streak_7'])->toBe(['accounts' => 1, 'share' => 0.3333])
        ->and([$short->id, $three->id, $seven->id])->toHaveCount(3);
});

it('tells the retention of the accounts that ever reached a streak of three apart from the others', function () {
    // Made 40 days ago: D7, D14 and D30 are over. Two reached a streak of three (one of them came back at D7 and D30), two did not (one came back at D14).
    $a = streakAccount(40, 0, 4, 7);
    UserVisit::factory()->create(['user_id' => $a->id, 'at' => CarbonImmutable::instance($a->created_at)->startOfDay()->addDays(30)->addHours(12)]);
    streakAccount(40, 0, 3, null);
    streakAccount(40, 0, 1, 14);
    streakAccount(40, 0, 0, null);

    $retention = streakReport()['retention'];

    expect($retention['with_streak']['accounts'])->toBe(2)->and($retention['without_streak']['accounts'])->toBe(2)
        ->and($retention['with_streak']['retention']['d7'])->toBe(['eligible' => 2, 'returned' => 1, 'rate' => 0.5])
        ->and($retention['with_streak']['retention']['d14']['returned'])->toBe(0)
        ->and($retention['with_streak']['retention']['d30']['returned'])->toBe(1)
        ->and($retention['without_streak']['retention']['d14'])->toBe(['eligible' => 2, 'returned' => 1, 'rate' => 0.5]);
});

it('counts the streaks that were lost in each of the last four weeks', function () {
    streakAccount(30, 0, 5, null, now()->subDays(2)->toDateString());
    streakAccount(30, 0, 5, null, now()->subDays(3)->toDateString());
    streakAccount(30, 0, 5, null, now()->subDays(10)->toDateString());
    streakAccount(30, 0, 5, null, now()->subDays(40)->toDateString());

    $weeks = streakReport()['broken_per_week'];

    expect(array_column($weeks, 'broken'))->toBe([2, 1, 0, 0])->and($weeks)->toHaveCount(4);
});

it('is printed by metrics:report', function () {
    streakAccount(20, 3, 3, 19);

    $this->artisan('metrics:report', ['--days' => 30])->expectsOutputToContain('The streak of days: of the 1 drivers who came in the last seven days')->assertSuccessful();
});
