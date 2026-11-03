<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Gig;
use App\Models\LedgerEntry;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Travel;
use App\Models\TutorialProgress;
use App\Models\User;
use App\Models\UserMissionPeriod;
use App\Models\UserStreak;
use App\Models\UserVisit;
use App\Models\WorkshopJob;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The numbers of the alpha (the gate M5 of the roadmap: do the drivers come back after two or three days?), worked out from what the game keeps anyway: when an
 * account was made, when its driver visited (`user_visits`), and when it first did what the first fifteen minutes are meant to lead to. Nothing is sent anywhere and
 * nothing more is written down for it.
 *
 * A cohort is the accounts made in a period. A day is a calendar day of the time zone of the application.
 */
final class Metrics
{
    /** Visits closer than this belong to one session. */
    public const SESSION_BREAK_MINUTES = 30;

    /** A visit lasts at least this long: it is written when the driver comes, and again after every break of this length (`TouchLastSeen`). */
    public const VISIT_MINUTES = 10;

    /** The days after the day an account was made that the retention is told for. */
    public const RETENTION_DAYS = [1, 2, 3, 7];

    /** What the first fifteen minutes should lead to (the targets of the gate M5): a first upgrade within this many minutes, a first other city within these. */
    public const FIRST_UPGRADE_TARGET_MINUTES = 3;

    public const FIRST_JOURNEY_TARGET_MINUTES = 30;

    /** The number of steps of the tutorial that are counted as far enough. */
    public const TUTORIAL_REACHED = [5, 10, 20];

    /**
     * The report for the accounts made from `$from` (inclusive) to `$to` (exclusive), as of `$now`.
     *
     * @return array{
     *     cohort: array{from: string, to: string, accounts: int, secured: int, guests: int},
     *     retention: array<string, array{eligible: int, returned: int, rate: float|null}>,
     *     sessions: array{drivers: int, sessions: int, per_driver: float|null, median_minutes: float|null},
     *     activity: list<array{day: string, active: int, new: int, returning: int}>,
     *     first_steps: array<string, array{reached: int, rate: float|null, median_minutes: float|null, within_target: int|null, target_minutes: int|null}>,
     *     tutorial: array<string, array{reached: int, rate: float|null}>,
     *     missions: array{days: list<array{day: string, drawn: int, done: int, bonus_taken: int}>, coins_30_days: int},
     *     gigs: array{days: list<array{day: string, taken: int, drivers: int, paid_cents: int}>, paid_cents_30_days: int},
     *     streaks: array{active: int, streak_3: array{accounts: int, share: float|null}, streak_7: array{accounts: int, share: float|null}, retention: array<string, array{accounts: int, retention: array<string, array{eligible: int, returned: int, rate: float|null}>}>, broken_per_week: list<array{from: string, to: string, broken: int}>}
     * }
     */
    public function report(CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $accounts = User::query()->where('created_at', '>=', $from)->where('created_at', '<', $to)->get(['id', 'created_at', 'claimed_at']);
        $visits = $this->visitsOf($accounts->pluck('id')->all());

        return [
            'cohort' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'accounts' => $accounts->count(),
                'secured' => $accounts->whereNotNull('claimed_at')->count(),
                'guests' => $accounts->whereNull('claimed_at')->count(),
            ],
            'retention' => $this->retention($accounts, $visits, $now),
            'sessions' => $this->sessions($visits),
            'activity' => $this->activity($now),
            'first_steps' => $this->firstSteps($accounts),
            'tutorial' => $this->tutorial($accounts),
            'missions' => $this->missions($now),
            'gigs' => $this->gigs($now),
            'streaks' => $this->streaks($accounts, $visits, $now),
        ];
    }

    /**
     * The streak of days (retention, stage B): of the drivers who came in the last seven days, how many have a streak of three days and of seven; the retention of the accounts of the
     * cohort at D7, D14 and D30, by whether they ever reached a streak of three (which is a comparison of survivors: those who keep a streak are those who come back, so it says how the
     * two go together and not what the streak causes); and how many streaks were lost in each of the last four weeks (a driver counts for the last streak they lost).
     *
     * @param  Collection<int, User>  $accounts
     * @param  Collection<int, Collection<int, CarbonImmutable>>  $visits
     * @return array{active: int, streak_3: array{accounts: int, share: float|null}, streak_7: array{accounts: int, share: float|null}, retention: array<string, array{accounts: int, retention: array<string, array{eligible: int, returned: int, rate: float|null}>}>, broken_per_week: list<array{from: string, to: string, broken: int}>}
     */
    private function streaks(Collection $accounts, Collection $visits, CarbonImmutable $now): array
    {
        $active = UserVisit::query()->where('at', '>=', $now->subDays(7))->distinct()->pluck('user_id');
        $current = UserStreak::query()->whereIn('user_id', $active->all())->pluck('current', 'user_id');
        $share = fn (int $count): ?float => $active->count() === 0 ? null : round($count / $active->count(), 4);
        $atLeast = fn (int $days): int => $current->filter(fn (int $length): bool => $length >= $days)->count();

        $best = UserStreak::query()->whereIn('user_id', $accounts->pluck('id')->all())->pluck('best', 'user_id');
        $groups = [
            'with_streak' => $accounts->filter(fn (User $account): bool => ($best[$account->id] ?? 0) >= 3),
            'without_streak' => $accounts->filter(fn (User $account): bool => ($best[$account->id] ?? 0) < 3),
        ];
        $retention = [];

        foreach ($groups as $name => $group) {
            $retention[$name] = ['accounts' => $group->count(), 'retention' => $this->retention($group, $visits->only($group->pluck('id')->all()), $now, [7, 14, 30])];
        }

        $weeks = [];

        for ($week = 0; $week < 4; $week++) {
            $to = $now->startOfDay()->subDays(7 * $week);
            $from = $to->subDays(7);
            $weeks[] = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'broken' => UserStreak::query()->where('lost', '>', 0)->where('lost_day', '>', $from->toDateString())->where('lost_day', '<=', $to->toDateString())->count(),
            ];
        }

        return [
            'active' => $active->count(),
            'streak_3' => ['accounts' => $atLeast(3), 'share' => $share($atLeast(3))],
            'streak_7' => ['accounts' => $atLeast(7), 'share' => $share($atLeast(7))],
            'retention' => $retention,
            'broken_per_week' => $weeks,
        ];
    }

    /**
     * Whether the notifications make the drivers come back or not: the accounts made from `$from` to `$to` in the two groups of the experiment (told by push, and the holdout that is never
     * told, see `PushArm`), the retention and the sessions of each, the difference of the retention with the interval that 95 of 100 such comparisons would hold, and, for the messages that
     * were queued in the same time, how many were opened and how many were followed by a visit within an hour.
     *
     * The interval is the reason the report says the number of accounts: with few of them it is wide and says nothing, and the difference is only a direction. The drivers of the told group
     * who have a device are counted too, and comparing only them with the holdout is a biased comparison (those who agree to be told are those who would have come back anyway).
     *
     * @return array{
     *     arms: array<string, array{accounts: int, retention: array<string, array{eligible: int, returned: int, rate: float|null}>, sessions: array{drivers: int, sessions: int, per_driver: float|null, median_minutes: float|null}}>,
     *     subscribed: array{accounts: int, retention: array<string, array{eligible: int, returned: int, rate: float|null}>},
     *     difference: array<string, array{diff: float, low: float, high: float}|null>,
     *     messages: array{sent: int, opened: int, opened_rate: float|null, returned_within_hour: int, returned_rate: float|null}
     * }
     */
    public function byArm(CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $accounts = User::query()->where('created_at', '>=', $from)->where('created_at', '<', $to)->get(['id', 'created_at', 'claimed_at']);
        $visits = $this->visitsOf($accounts->pluck('id')->all());
        $arm = app(PushArm::class);
        $groups = [PushArm::PUSH => collect(), PushArm::HOLDOUT => collect()];

        foreach ($accounts as $account) {
            $groups[$arm->of($account->id)]->push($account);
        }

        $arms = [];

        foreach ($groups as $name => $group) {
            $own = $visits->only($group->pluck('id')->all());
            $arms[$name] = ['accounts' => $group->count(), 'retention' => $this->retention($group, $own, $now), 'sessions' => $this->sessions($own)];
        }

        $withDevice = PushSubscription::query()->withoutGlobalScopes()->whereIn('user_id', $groups[PushArm::PUSH]->pluck('id')->all())->distinct()->pluck('user_id')->all();
        $subscribed = $groups[PushArm::PUSH]->whereIn('id', $withDevice);

        $difference = [];

        foreach (self::RETENTION_DAYS as $days) {
            $difference["d{$days}"] = $this->difference($arms[PushArm::PUSH]['retention']["d{$days}"], $arms[PushArm::HOLDOUT]['retention']["d{$days}"]);
        }

        return [
            'arms' => $arms,
            'subscribed' => ['accounts' => $subscribed->count(), 'retention' => $this->retention($subscribed, $visits->only($subscribed->pluck('id')->all()), $now)],
            'difference' => $difference,
            'messages' => $this->messages($from, $to),
        ];
    }

    /**
     * The difference of two rates (told minus not told) with its interval of 95 of 100 (the normal approximation), or null when one of the groups has nobody to count.
     *
     * @param  array{eligible: int, returned: int, rate: float|null}  $told
     * @param  array{eligible: int, returned: int, rate: float|null}  $held
     * @return array{diff: float, low: float, high: float}|null
     */
    private function difference(array $told, array $held): ?array
    {
        if ($told['eligible'] === 0 || $held['eligible'] === 0) {
            return null;
        }

        $p1 = $told['returned'] / $told['eligible'];
        $p2 = $held['returned'] / $held['eligible'];
        $error = sqrt($p1 * (1 - $p1) / $told['eligible'] + $p2 * (1 - $p2) / $held['eligible']);

        return ['diff' => round($p1 - $p2, 4), 'low' => round($p1 - $p2 - 1.96 * $error, 4), 'high' => round($p1 - $p2 + 1.96 * $error, 4)];
    }

    /**
     * The messages queued from `$from` to `$to`: how many, how many were opened, and how many were followed by a visit of the driver within an hour.
     *
     * @return array{sent: int, opened: int, opened_rate: float|null, returned_within_hour: int, returned_rate: float|null}
     */
    private function messages(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $deliveries = PushDelivery::query()->withoutGlobalScopes()->where('created_at', '>=', $from)->where('created_at', '<', $to)->get(['user_id', 'created_at', 'opened_at']);
        $visits = UserVisit::query()->whereIn('user_id', $deliveries->pluck('user_id')->unique()->all())->where('at', '>=', $from)->get(['user_id', 'at'])->groupBy('user_id');
        $returned = $deliveries->filter(function (PushDelivery $delivery) use ($visits): bool {
            $sent = CarbonImmutable::instance($delivery->created_at);

            return ($visits->get($delivery->user_id) ?? collect())->contains(fn (UserVisit $visit): bool => $visit->at->greaterThan($sent) && $visit->at->lessThanOrEqualTo($sent->addHour()));
        })->count();
        $total = $deliveries->count();
        $opened = $deliveries->whereNotNull('opened_at')->count();

        return [
            'sent' => $total,
            'opened' => $opened,
            'opened_rate' => $total === 0 ? null : round($opened / $total, 4),
            'returned_within_hour' => $returned,
            'returned_rate' => $total === 0 ? null : round($returned / $total, 4),
        ];
    }

    /**
     * The visits of some accounts by account, oldest first.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Collection<int, CarbonImmutable>>
     */
    private function visitsOf(array $ids): Collection
    {
        $visits = collect();

        foreach (array_chunk($ids, 500) as $chunk) {
            $visits = $visits->concat(UserVisit::query()->whereIn('user_id', $chunk)->orderBy('user_id')->orderBy('at')->get(['user_id', 'at']));
        }

        return $visits->groupBy('user_id')->map(fn (Collection $rows): Collection => $rows->map(fn (UserVisit $visit): CarbonImmutable => CarbonImmutable::instance($visit->at))->values());
    }

    /**
     * Of the accounts for which the day has gone, how many visited exactly that many days after the day they were made.
     *
     * @param  Collection<int, User>  $accounts
     * @param  Collection<int, Collection<int, CarbonImmutable>>  $visits
     * @param  list<int>  $daysTold
     * @return array<string, array{eligible: int, returned: int, rate: float|null}>
     */
    private function retention(Collection $accounts, Collection $visits, CarbonImmutable $now, array $daysTold = self::RETENTION_DAYS): array
    {
        $result = [];

        foreach ($daysTold as $days) {
            $eligible = 0;
            $returned = 0;

            foreach ($accounts as $account) {
                $day = CarbonImmutable::instance($account->created_at)->startOfDay()->addDays($days);

                // The day has to be over, or the ones who have not yet had the chance would count as lost.
                if ($day->addDay()->greaterThan($now)) {
                    continue;
                }

                $eligible++;

                if (($visits->get($account->id) ?? collect())->contains(fn (CarbonImmutable $at): bool => $at->toDateString() === $day->toDateString())) {
                    $returned++;
                }
            }

            $result["d{$days}"] = ['eligible' => $eligible, 'returned' => $returned, 'rate' => $eligible === 0 ? null : round($returned / $eligible, 4)];
        }

        return $result;
    }

    /**
     * Sessions: the visits of a driver that follow each other with less than `SESSION_BREAK_MINUTES` between them. A session lasted from its first visit to its last,
     * and at least as long as one visit does.
     *
     * @param  Collection<int, Collection<int, CarbonImmutable>>  $visits
     * @return array{drivers: int, sessions: int, per_driver: float|null, median_minutes: float|null}
     */
    private function sessions(Collection $visits): array
    {
        $lengths = [];

        foreach ($visits as $own) {
            $start = null;
            $last = null;

            foreach ($own as $at) {
                if ($last !== null && $last->diffInMinutes($at, true) >= self::SESSION_BREAK_MINUTES) {
                    $lengths[] = (int) round($start->diffInMinutes($last, true)) + self::VISIT_MINUTES;
                    $start = null;
                }

                $start ??= $at;
                $last = $at;
            }

            if ($start !== null && $last !== null) {
                $lengths[] = (int) round($start->diffInMinutes($last, true)) + self::VISIT_MINUTES;
            }
        }

        $drivers = $visits->count();

        return [
            'drivers' => $drivers,
            'sessions' => count($lengths),
            'per_driver' => $drivers === 0 ? null : round(count($lengths) / $drivers, 2),
            'median_minutes' => $this->median($lengths),
        ];
    }

    /**
     * Who came on each of the last fourteen days, all accounts: how many, how many of them were made that day and how many were back.
     *
     * @return list<array{day: string, active: int, new: int, returning: int}>
     */
    private function activity(CarbonImmutable $now): array
    {
        $first = $now->startOfDay()->subDays(13);
        $byDay = UserVisit::query()->where('at', '>=', $first)->get(['user_id', 'at'])
            ->groupBy(fn (UserVisit $visit): string => CarbonImmutable::instance($visit->at)->toDateString())
            ->map(fn (Collection $rows): array => $rows->pluck('user_id')->unique()->values()->all());
        $made = User::query()->where('created_at', '>=', $first)->get(['id', 'created_at'])
            ->groupBy(fn (User $user): string => CarbonImmutable::instance($user->created_at)->toDateString());
        $rows = [];

        for ($i = 0; $i < 14; $i++) {
            $day = $first->addDays($i)->toDateString();
            $active = $byDay->get($day, []);
            $new = ($made->get($day) ?? collect())->pluck('id')->intersect($active)->count();

            $rows[] = ['day' => $day, 'active' => count($active), 'new' => $new, 'returning' => count($active) - $new];
        }

        return $rows;
    }

    /**
     * How the missions of the days are done, for the last fourteen days and all accounts: how many sets were drawn, how many were done whole and how many of those
     * took their bonus; and how many premium coins the bonuses gave in thirty days.
     *
     * @return array{days: list<array{day: string, drawn: int, done: int, bonus_taken: int}>, coins_30_days: int}
     */
    private function missions(CarbonImmutable $now): array
    {
        $first = $now->startOfDay()->subDays(13);
        $sets = UserMissionPeriod::query()->where('kind', 'daily')->where('period_key', '>=', $first->toDateString())->get(['period_key', 'completed_at', 'bonus_claimed_at'])->groupBy('period_key');
        $days = [];

        for ($i = 0; $i < 14; $i++) {
            $day = $first->addDays($i)->toDateString();
            $rows = $sets->get($day, collect());

            $days[] = ['day' => $day, 'drawn' => $rows->count(), 'done' => $rows->whereNotNull('completed_at')->count(), 'bonus_taken' => $rows->whereNotNull('bonus_claimed_at')->count()];
        }

        $coins = (int) LedgerEntry::query()->where('currency', 'premium')->where('reason', 'mission_bonus')->where('created_at', '>=', $now->subDays(30))->sum('delta');

        return ['days' => $days, 'coins_30_days' => $coins];
    }

    /**
     * How the gigs are taken, for the last fourteen days and all accounts: how many were taken (the ones taken back are not counted), by how many drivers, and what the ones that
     * were paid paid; and what they paid in thirty days. It says whether the gigs are a second loop that drivers use, and how much of the income they are.
     *
     * @return array{days: list<array{day: string, taken: int, drivers: int, paid_cents: int}>, paid_cents_30_days: int}
     */
    private function gigs(CarbonImmutable $now): array
    {
        $first = $now->startOfDay()->subDays(13);
        $taken = Gig::query()->where('status', '!=', Gig::STATUS_CANCELLED)->where('start_ms', '>=', $first->getTimestampMs())->get(['user_id', 'start_ms', 'reward_cents', 'paid_cents', 'status'])
            ->groupBy(static fn (Gig $gig): string => CarbonImmutable::createFromTimestampMs($gig->start_ms)->toDateString());
        $days = [];

        for ($i = 0; $i < 14; $i++) {
            $day = $first->addDays($i)->toDateString();
            $rows = $taken->get($day, collect());

            $days[] = ['day' => $day, 'taken' => $rows->count(), 'drivers' => $rows->pluck('user_id')->unique()->count(), 'paid_cents' => (int) $rows->where('status', Gig::STATUS_DONE)->sum(static fn (Gig $gig): int => (int) ($gig->paid_cents ?? $gig->reward_cents))];
        }

        $paid = (int) LedgerEntry::query()->where('currency', 'cash')->where('reason', 'gig')->where('created_at', '>=', $now->subDays(30))->sum('delta');

        return ['days' => $days, 'paid_cents_30_days' => $paid];
    }

    /**
     * How long after the account was made the driver first queued an upgrade and first sent a car to another city, and how many did it within the targets.
     *
     * @param  Collection<int, User>  $accounts
     * @return array<string, array{reached: int, rate: float|null, median_minutes: float|null, within_target: int|null, target_minutes: int|null}>
     */
    private function firstSteps(Collection $accounts): array
    {
        $ids = $accounts->pluck('id')->all();
        $made = $accounts->mapWithKeys(fn (User $user): array => [$user->id => CarbonImmutable::instance($user->created_at)]);

        $upgrade = $this->firstAt(WorkshopJob::class, $ids, ['kind' => WorkshopJob::KIND_UPGRADE]);
        $journey = $this->firstAt(Travel::class, $ids);

        return [
            'first_upgrade' => $this->step($made, $upgrade, self::FIRST_UPGRADE_TARGET_MINUTES),
            'first_journey' => $this->step($made, $journey, self::FIRST_JOURNEY_TARGET_MINUTES),
        ];
    }

    /**
     * The first time each account did something, from the rows of a table that have a `created_at`.
     *
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids
     * @param  array<string, string>  $where
     * @return Collection<int, CarbonImmutable>
     */
    private function firstAt(string $model, array $ids, array $where = []): Collection
    {
        $first = collect();

        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = $model::query()->whereIn('user_id', $chunk)->where($where)->orderBy('created_at')->get(['user_id', 'created_at']);

            foreach ($rows as $row) {
                if (! $first->has($row->user_id)) {
                    $first->put($row->user_id, CarbonImmutable::instance($row->created_at));
                }
            }
        }

        return $first;
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $made
     * @param  Collection<int, CarbonImmutable>  $first
     * @return array{reached: int, rate: float|null, median_minutes: float|null, within_target: int|null, target_minutes: int|null}
     */
    private function step(Collection $made, Collection $first, int $targetMinutes): array
    {
        $minutes = $first->map(fn (CarbonImmutable $at, int $id): float => max(0.0, $made[$id]->diffInSeconds($at, true) / 60))->values()->all();

        return [
            'reached' => count($minutes),
            'rate' => $made->isEmpty() ? null : round(count($minutes) / $made->count(), 4),
            'median_minutes' => $this->median($minutes),
            'within_target' => count(array_filter($minutes, static fn (float $m): bool => $m <= $targetMinutes)),
            'target_minutes' => $targetMinutes,
        ];
    }

    /**
     * How many accounts finished at least this many steps of the tutorial.
     *
     * @param  Collection<int, User>  $accounts
     * @return array<string, array{reached: int, rate: float|null}>
     */
    private function tutorial(Collection $accounts): array
    {
        $done = collect();

        foreach (array_chunk($accounts->pluck('id')->all(), 500) as $chunk) {
            $done = $done->concat(TutorialProgress::query()->whereIn('user_id', $chunk)->where('state', 'done')->get(['user_id']));
        }

        $steps = $done->countBy('user_id');
        $result = [];

        foreach (self::TUTORIAL_REACHED as $count) {
            $reached = $steps->filter(fn (int $n): bool => $n >= $count)->count();
            $result["steps_{$count}"] = ['reached' => $reached, 'rate' => $accounts->isEmpty() ? null : round($reached / $accounts->count(), 4)];
        }

        return $result;
    }

    /** @param  array<int, int|float>  $values */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return round(count($values) % 2 === 1 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2, 1);
    }
}
