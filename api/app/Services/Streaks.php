<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Exceptions\RuleRefusedException;
use App\Models\StreakDay;
use App\Models\UserMission;
use App\Models\UserMissionPeriod;
use App\Models\UserStreak;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The streak of days of a driver: the days of the missions in a row on which they did the goal of the day (a number of the missions of the daily set), counted out of
 * the missions the game keeps anyway, so nothing new has to be asked of the driver and nothing is counted twice. A day that was missed is saved by a shield if the
 * driver has one (the streak stands where it is, and the day pays nothing), and ends the streak if they have not. A day that counts pays what the rules say for its
 * place in the cycle, and a milestone pays on top, once; what it pays is worked out when the day counts, from a tank of the best car of the driver, as the missions
 * do, so it follows the economy of the game. It pays no premium coins.
 *
 * The days are settled whenever somebody looks (`settle`, from the missions), in order, from the day after the last one that was settled: the days between are
 * counted out of the sets that were drawn for them (a day on which the driver never looked has no set and is missed), and today is counted as soon as its goal is done.
 * The reward of a day is taken by the driver (a button) or, when the day is over, by the server for them; both under the lock of the wallet and a key of the day.
 */
final class Streaks
{
    /** A driver who was away for more days than this has no streak anyway (the shields would not cover them), so the days are not walked one by one. */
    private const MAX_DAYS_WALKED = 400;

    public function __construct(
        private readonly Rules $rules,
        private readonly MissionPeriods $periods,
        private readonly MissionScale $scale,
        private readonly RewardPayer $payer,
    ) {}

    /**
     * Brings the streak of the driver up to date: settles the days that are over, counts today if its goal is done, and takes for the driver what the days that
     * are over paid. The caller holds the lock of the wallet and the transaction, has closed the periods that ended and has counted the daily set of today.
     */
    public function settle(int $userId, CarbonInterface $now): void
    {
        if (! $this->rules->streaksOn()) {
            return;
        }

        $settings = $this->rules->streaks();
        $today = $this->periods->at('daily', $now)['key'];
        $row = $this->row($userId);

        // The days to settle begin after the last one that was, or, for a driver whose streak has never been settled, with the first set they were ever drawn: a day on
        // which they did the goal and did not look again is a day that counts.
        $first = $row->last_day !== null
            ? CarbonImmutable::parse("{$row->last_day} 00:00:00", 'UTC')->addDay()->format('Y-m-d')
            : UserMissionPeriod::query()->where('user_id', $userId)->where('kind', 'daily')->min('period_key');

        if ($first !== null) {
            $day = CarbonImmutable::parse("{$first} 00:00:00", 'UTC');

            for ($walked = 0; $day->format('Y-m-d') < $today && $walked < self::MAX_DAYS_WALKED; $walked++) {
                $key = $day->format('Y-m-d');

                if ($this->goalDone($userId, $key, $settings['daily_done'])) {
                    $this->count($row, $key, $settings);
                } else {
                    $this->miss($row, $key);
                }

                // Nothing is left to save or to lose: the days that remain are all the same, and the last of them is yesterday.
                if ($row->current === 0 && $row->shields === 0) {
                    $row->forceFill(['last_day' => CarbonImmutable::parse("{$today} 00:00:00", 'UTC')->subDay()->format('Y-m-d')])->save();

                    break;
                }

                $day = $day->addDay();
            }
        }

        if (($row->last_day === null || $row->last_day < $today) && $this->goalDone($userId, $today, $settings['daily_done'])) {
            $this->count($row, $today, $settings);
        }

        $this->takeThatIsOver($userId, $today);
    }

    /**
     * Takes the reward of the days that count and were not taken, for the driver who pressed the button (the day of today, and any day that is over and was not
     * taken yet). Taking again takes nothing. The caller holds the lock of the wallet and the transaction, and has settled the streak.
     *
     * @return array{days: int, cash_cents: int, xp: int, car_xp: int, parts: list<int>, crates: list<string>}
     *
     * @throws RuleRefusedException `streak_unavailable`
     */
    public function claim(int $userId): array
    {
        if (! $this->rules->streaksOn()) {
            throw new RuleRefusedException('streak_unavailable');
        }

        $total = ['days' => 0, 'cash_cents' => 0, 'xp' => 0, 'car_xp' => 0, 'parts' => [], 'crates' => []];

        foreach (StreakDay::query()->where('user_id', $userId)->where('kind', StreakDay::KIND_GOAL)->whereNull('claimed_at')->orderBy('day_key')->lockForUpdate()->get() as $day) {
            $result = $this->pay($day, auto: false);
            $total = [
                'days' => $total['days'] + 1,
                'cash_cents' => $total['cash_cents'] + $result['cash_cents'],
                'xp' => $total['xp'] + $result['xp'],
                'car_xp' => $total['car_xp'] + $result['car_xp'],
                'parts' => [...$total['parts'], ...$result['parts']],
                'crates' => [...$total['crates'], ...$result['crates']],
            ];
        }

        return $total;
    }

    /** How many days wait for the driver to take what they paid: the number the screen shows next to the missions. */
    public function takeable(int $userId): int
    {
        return $this->rules->streaksOn() ? StreakDay::query()->where('user_id', $userId)->where('kind', StreakDay::KIND_GOAL)->whereNull('claimed_at')->count() : 0;
    }

    /**
     * The streak for the screen, at the moment of the call: how long it is, the shields, the goal of today and how far the driver is from it, what today pays (and
     * whether it was taken), what the next day pays, the cycle with the day the driver is at, and the next milestone. Null when the game has no streak.
     *
     * @return array<string, mixed>|null
     */
    public function view(int $userId, CarbonInterface $now): ?array
    {
        if (! $this->rules->streaksOn()) {
            return null;
        }

        $settings = $this->rules->streaks();
        $today = $this->periods->at('daily', $now)['key'];
        $row = UserStreak::query()->find($userId);
        $tankful = $this->scale->tankful($userId);
        $set = UserMissionPeriod::query()->where('user_id', $userId)->where('kind', 'daily')->where('period_key', $today)->first();
        $needed = $set === null ? $settings['daily_done'] : min($settings['daily_done'], $set->missions);
        $done = $set === null ? 0 : UserMission::query()->where('user_id', $userId)->where('kind', 'daily')->where('period_key', $today)->whereNotNull('completed_at')->count();
        $counted = StreakDay::query()->where('user_id', $userId)->where('day_key', $today)->where('kind', StreakDay::KIND_GOAL)->first();
        $length = count($settings['cycle']);
        $current = $row?->current ?? 0;
        $cycleDay = $row?->cycle_day ?? 0;
        // What the next day to count pays: the day after the one the driver is at (today, when today has not counted yet).
        $nextDay = ($cycleDay % $length) + 1;
        $nextStreak = $current + 1;
        $milestone = $this->nextMilestone($settings['milestones'], $current);

        $cycle = [];

        foreach ($settings['cycle'] as $index => $entry) {
            $cycle[] = ['day' => $index + 1, ...$this->amounts($entry, $tankful)];
        }

        return [
            'current' => $current,
            'best' => $row?->best ?? 0,
            'shields' => $row?->shields ?? 0,
            'shield_cap' => $settings['shield_cap'],
            'shield_every_days' => $settings['shield_every_days'],
            'goal' => ['needed' => $needed, 'done' => min($done, $needed), 'reached' => $counted !== null],
            'today' => $counted === null ? null : [
                'state' => $counted->claimed_at === null ? 'ready' : 'claimed',
                'streak' => $counted->streak,
                'cycle_day' => $counted->cycle_day,
                'reward' => $counted->reward,
            ],
            'next' => ['cycle_day' => $nextDay, 'streak' => $nextStreak, ...$this->amounts($this->rewardEntry($settings, $nextDay, $nextStreak), $tankful)],
            'cycle' => $cycle,
            'cycle_day' => $cycleDay,
            'milestone' => $milestone === null ? null : ['day' => $milestone, 'items' => $settings['milestones'][$milestone]['items']],
            'lost' => $row !== null && $row->lost > 0 && $row->current === 0 ? ['streak' => $row->lost, 'day' => $row->lost_day] : null,
        ];
    }

    /**
     * What the streak did while the driver was away, from the moment the break began: the days that counted, the shields that were used, the streak that was lost (its
     * length) and what the server took for the driver. Null when there is nothing to tell. The caller has brought the missions up to date.
     *
     * @return array{current: int, counted: int, shields_used: int, lost: int|null, cash_cents: int, xp: int}|null
     */
    public function awayReport(int $userId, CarbonInterface $from): ?array
    {
        if (! $this->rules->streaksOn()) {
            return null;
        }

        $row = UserStreak::query()->find($userId);
        $days = StreakDay::query()->where('user_id', $userId)->where('created_at', '>=', $from)->get();
        $taken = $days->where('auto_claimed', true);
        $lost = $row !== null && $row->lost > 0 && $row->updated_at !== null && $row->updated_at >= $from ? $row->lost : null;
        $report = [
            'current' => $row?->current ?? 0,
            'counted' => $days->where('kind', StreakDay::KIND_GOAL)->count(),
            'shields_used' => $days->where('kind', StreakDay::KIND_SHIELD)->count(),
            'lost' => $lost,
            'cash_cents' => (int) $taken->sum(fn (StreakDay $day): int => (int) ($day->claim_result['cash_cents'] ?? 0)),
            'xp' => (int) $taken->sum(fn (StreakDay $day): int => (int) ($day->claim_result['xp'] ?? 0)),
        ];

        return $report['counted'] === 0 && $report['shields_used'] === 0 && $lost === null ? null : $report;
    }

    private function row(int $userId): UserStreak
    {
        return UserStreak::query()->whereKey($userId)->lockForUpdate()->first()
            ?? UserStreak::query()->create(['user_id' => $userId, 'current' => 0, 'best' => 0, 'cycle_day' => 0, 'shields' => 0, 'lost' => 0]);
    }

    /** Whether the driver did the goal in the daily set of a day: enough of its missions are done (not necessarily taken). A day without a set is a day not done. */
    private function goalDone(int $userId, string $day, int $needed): bool
    {
        $set = UserMissionPeriod::query()->where('user_id', $userId)->where('kind', 'daily')->where('period_key', $day)->first();

        if ($set === null || $set->missions < 1) {
            return false;
        }

        $done = UserMission::query()->where('user_id', $userId)->where('kind', 'daily')->where('period_key', $day)->whereNotNull('completed_at')->count();

        return $done >= min($needed, $set->missions);
    }

    /**
     * A day counts: the streak is a day longer, the day of the cycle moves on, a shield is earned when the length is a multiple of the rules, and the day is written
     * down with what it pays.
     *
     * @param  array<string, mixed>  $settings
     */
    private function count(UserStreak $row, string $day, array $settings): void
    {
        $streak = $row->current + 1;
        $cycleDay = ($row->cycle_day % count($settings['cycle'])) + 1;

        StreakDay::query()->insertOrIgnore([
            'user_id' => $row->user_id,
            'day_key' => $day,
            'kind' => StreakDay::KIND_GOAL,
            'streak' => $streak,
            'cycle_day' => $cycleDay,
            'reward' => json_encode($this->reward($row->user_id, $settings, $cycleDay, $streak), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shields = $row->shields;

        if ($settings['shield_cap'] > 0 && $streak % $settings['shield_every_days'] === 0) {
            $shields = min($settings['shield_cap'], $shields + 1);
        }

        $row->forceFill(['current' => $streak, 'best' => max($row->best, $streak), 'cycle_day' => $cycleDay, 'shields' => $shields, 'last_day' => $day])->save();
    }

    /** A day was missed: a shield saves the streak (it stands, and the day pays nothing), and without one the streak ends. A driver with no streak loses nothing. */
    private function miss(UserStreak $row, string $day): void
    {
        if ($row->current === 0) {
            $row->forceFill(['last_day' => $day])->save();

            return;
        }

        if ($row->shields > 0) {
            StreakDay::query()->insertOrIgnore([
                'user_id' => $row->user_id,
                'day_key' => $day,
                'kind' => StreakDay::KIND_SHIELD,
                'streak' => $row->current,
                'cycle_day' => $row->cycle_day,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row->forceFill(['shields' => $row->shields - 1, 'last_day' => $day])->save();

            return;
        }

        $row->forceFill(['lost' => $row->current, 'lost_day' => $day, 'current' => 0, 'cycle_day' => 0, 'last_day' => $day])->save();
    }

    /** The server takes what the days that are over paid and the driver did not take. */
    private function takeThatIsOver(int $userId, string $today): void
    {
        foreach (StreakDay::query()->where('user_id', $userId)->where('kind', StreakDay::KIND_GOAL)->whereNull('claimed_at')->where('day_key', '<', $today)->orderBy('day_key')->get() as $day) {
            $this->pay($day, auto: true);
        }
    }

    /** @return array{cash_cents: int, xp: int, car_xp: int, parts: list<int>, crates: list<string>} */
    private function pay(StreakDay $day, bool $auto): array
    {
        $key = "streak:{$day->user_id}:{$day->day_key}";
        $result = $this->payer->payMission($day->user_id, $day->reward ?? [], $key, $key, 'streak');

        $day->forceFill(['claimed_at' => now(), 'auto_claimed' => $auto, 'claim_result' => $result])->save();

        return $result;
    }

    /**
     * What the day of the cycle (and the milestone of the length of the streak, if there is one) pays, from a tank of the best car of the driver.
     *
     * @param  array<string, mixed>  $settings
     * @return array{cash_cents: int, xp: int, items: list<array<string, string>>}
     */
    private function reward(int $userId, array $settings, int $cycleDay, int $streak): array
    {
        return $this->amounts($this->rewardEntry($settings, $cycleDay, $streak), $this->scale->tankful($userId));
    }

    /**
     * The day of the cycle and the milestone together, in parts of a tank.
     *
     * @param  array<string, mixed>  $settings
     * @return array{cash_ppm: int, xp_ppm: int, items: list<array<string, string>>}
     */
    private function rewardEntry(array $settings, int $cycleDay, int $streak): array
    {
        $entry = $settings['cycle'][$cycleDay - 1];
        $milestone = $settings['milestones'][$streak] ?? ['cash_ppm' => 0, 'xp_ppm' => 0, 'items' => []];

        return [
            'cash_ppm' => $entry['cash_ppm'] + $milestone['cash_ppm'],
            'xp_ppm' => $entry['xp_ppm'] + $milestone['xp_ppm'],
            'items' => [...$entry['items'], ...$milestone['items']],
        ];
    }

    /**
     * Parts of a tank turned into cash and XP: the cash a part of the net earnings of a tank (whole dollars, and at least a dollar where the rules pay anything), the XP
     * a part of the XP of it (at least a point). A driver with no car that can drive a tank gets the items only.
     *
     * @param  array{cash_ppm: int, xp_ppm: int, items: list<array<string, string>>}  $entry
     * @param  array{laps: int, lap_cash_cents: int, gross_cents: int, net_cents: int, xp: int, fuel_cents: int}|null  $tankful
     * @return array{cash_cents: int, xp: int, items: list<array<string, string>>}
     */
    private function amounts(array $entry, ?array $tankful): array
    {
        return [
            'cash_cents' => $tankful !== null && $entry['cash_ppm'] > 0 ? max(100, intdiv((int) round(max(0, $tankful['net_cents']) * $entry['cash_ppm'] / 1_000_000) + 50, 100) * 100) : 0,
            'xp' => $tankful !== null && $entry['xp_ppm'] > 0 ? max(1, (int) round($tankful['xp'] * $entry['xp_ppm'] / 1_000_000)) : 0,
            'items' => $entry['items'],
        ];
    }

    /**
     * The first milestone above a length, or null when there is none.
     *
     * @param  array<int, mixed>  $milestones
     */
    private function nextMilestone(array $milestones, int $length): ?int
    {
        $days = array_filter(array_keys($milestones), static fn (int $day): bool => $day > $length);

        return $days === [] ? null : min($days);
    }
}
