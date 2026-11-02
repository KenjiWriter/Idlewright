<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\Rules;
use App\Models\RaceRun;
use App\Services\MissionScale;
use Illuminate\Console\Command;

/**
 * What the streak of days pays, for a car on a track: the day of every place in the cycle, the sum of a week and of a month of days in a row (the milestones in it), and
 * what that is next to the earnings of the three kinds of player of the economy simulator, taking a tank for every visit they make in a day. The tool for tuning the
 * numbers of the rules, and for seeing what a change does before it is published; it warns when the streak pays more than it should for a reward that is outside the
 * economy of the game.
 */
final class StreaksBudget extends Command
{
    use RunsInAGame;

    /** The visits in a day of the players A, B and C of the economy simulator (`players` of rules/economy): a tank for every visit. */
    private const VISITS = ['A' => 3, 'B' => 1, 'C' => 8];

    /** The most the streak should pay of what a month of the cars earns, per kind of player: a gift that is outside the economy has to stay small. */
    private const LIMIT_PERCENT = ['A' => 5, 'B' => 10, 'C' => 5];

    protected $signature = 'streaks:budget
        {--car=rdzak-gt-4 : The slug of the car whose tank is the measure}
        {--track=zlomowo-street : The slug of the track it drives}
        {--days=30 : The days of the month to add up}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Show what the streak of days pays, for a car on a track';

    public function handle(Rules $rules, MissionScale $scale): int
    {
        $settings = $rules->streaks();

        if ($settings === null) {
            $this->error('The rules of this game have no streak.');

            return self::FAILURE;
        }

        $run = new RaceRun(['car_slug' => (string) $this->option('car'), 'track_slug' => (string) $this->option('track')]);
        $tank = $scale->tankOf($run);

        if ($tank === null) {
            $this->error('That car cannot drive that track on its tank.');

            return self::FAILURE;
        }

        $dollars = static fn (int|float $cents): string => '$'.number_format($cents / 100, 2);
        $this->info(sprintf('A tank of the %s on %s: %d laps, %s after the fuel, %d XP. The streak is %s.', $this->option('car'), $this->option('track'), $tank['laps'], $dollars($tank['net_cents']), $tank['xp'], $settings['enabled'] ? 'on' : 'OFF'));

        $cash = static fn (int $ppm): int => $ppm > 0 ? max(100, intdiv((int) round(max(0, $tank['net_cents']) * $ppm / 1_000_000) + 50, 100) * 100) : 0;
        $xp = static fn (int $ppm): int => $ppm > 0 ? max(1, (int) round($tank['xp'] * $ppm / 1_000_000)) : 0;
        $crates = static fn (array $items): int => count(array_filter($items, static fn (array $item): bool => $item['type'] === 'crate'));

        $rows = [];
        $week = ['cash' => 0, 'xp' => 0, 'crates' => 0];

        foreach ($settings['cycle'] as $index => $entry) {
            $rows[] = [$index + 1, $dollars($cash($entry['cash_ppm'])), $xp($entry['xp_ppm']), implode(', ', array_map(static fn (array $item): string => "crate {$item['kind']}", $entry['items']))];
            $week['cash'] += $cash($entry['cash_ppm']);
            $week['xp'] += $xp($entry['xp_ppm']);
            $week['crates'] += $crates($entry['items']);
        }

        $this->table(['day of the cycle', 'cash', 'XP', 'items'], $rows);

        $days = max(1, (int) $this->option('days'));
        $length = count($settings['cycle']);
        $month = ['cash' => 0, 'xp' => 0, 'crates' => 0];

        for ($streak = 1; $streak <= $days; $streak++) {
            $entry = $settings['cycle'][($streak - 1) % $length];
            $milestone = $settings['milestones'][$streak] ?? ['cash_ppm' => 0, 'xp_ppm' => 0, 'items' => []];

            $month['cash'] += $cash($entry['cash_ppm']) + $cash($milestone['cash_ppm']);
            $month['xp'] += $xp($entry['xp_ppm']) + $xp($milestone['xp_ppm']);
            $month['crates'] += $crates($entry['items']) + $crates($milestone['items']);
        }

        $this->newLine();
        $this->line(sprintf('A cycle of %d days pays %s, %d XP and %d crates. %d days in a row (milestones included) pay %s, %d XP and %d crates.', $length, $dollars($week['cash']), $week['xp'], $week['crates'], $days, $dollars($month['cash']), $month['xp'], $month['crates']));

        $warned = false;

        foreach (self::VISITS as $kind => $visits) {
            $earned = max(1, $days * $visits * $tank['net_cents']);
            $earnedXp = max(1, $days * $visits * $tank['xp']);
            $percent = 100 * $month['cash'] / $earned;
            $over = $percent > self::LIMIT_PERCENT[$kind];
            $warned = $warned || $over;

            $this->line(sprintf('Player %s (%d tanks a day): the cars earn %s in %d days, the streak adds %.1f%% in cash and %.1f%% in XP, and %.1f crates a week.%s', $kind, $visits, $dollars($earned), $days, $percent, 100 * $month['xp'] / $earnedXp, $month['crates'] * 7 / $days, $over ? sprintf(' MORE THAN %d%%.', self::LIMIT_PERCENT[$kind]) : ''));
        }

        $this->line('The streak pays no premium coins. The crates are free crates of their kind: their effect on the pace of the game is for the economy simulator (an extra of free crates), not measured here.');

        return $warned ? self::FAILURE : self::SUCCESS;
    }
}
