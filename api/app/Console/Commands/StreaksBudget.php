<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Content\GameCrates;
use App\Content\Rules;
use App\Domain\Economy\EconomyRules;
use App\Domain\Economy\RetentionStudy;
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
        {--simulate : Play the streak in the economy simulator and say whether it moves the day a car is bought by more than 5%}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Show what the streak of days pays, for a car on a track';

    public function handle(Rules $rules, MissionScale $scale, RetentionStudy $study, GameCrates $gameCrates): int
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

        if ($this->option('simulate')) {
            $warned = $this->simulate($rules, $study, $gameCrates) || $warned;
        }

        return $warned ? self::FAILURE : self::SUCCESS;
    }

    /** Plays the streak with the three kinds of players of the economy simulator, and says whether it moves the pace of the game by more than the gate lets a gift. Whether it failed. */
    private function simulate(Rules $rules, RetentionStudy $study, GameCrates $crates): bool
    {
        $settings = $rules->streaks();
        $items = static fn (array $entry): int => count(array_filter($entry['items'], static fn (array $item): bool => $item['type'] === 'crate'));
        $shape = static fn (array $entry): array => ['cash_ppm' => $entry['cash_ppm'], 'xp_ppm' => $entry['xp_ppm'], 'crates' => $items($entry)];
        $economy = EconomyRules::fromArray(json_decode((string) file_get_contents(config('ride.content_path').'/rules/economy.json'), true, 512, JSON_THROW_ON_ERROR));
        $result = $study->streak($economy, $crates->economy(), array_map($shape, $settings['cycle']), array_map($shape, $settings['milestones']));

        $this->newLine();
        $this->line('The streak in the economy simulator: a player who never breaks it, a free crate valued at its price (the most it can be worth).');
        $rows = [];

        foreach ($result['runs'] as $key => $run) {
            $note = $run->notes['streak'] ?? [];
            $rows[] = [$key, $this->percent($note['uplift_cash_30d'] ?? null), $this->percent($note['uplift_xp_30d'] ?? null)];
        }

        $this->table(['player', 'cash added (first 30 days)', 'XP added (first 30 days)'], $rows);

        $failures = $result['gate']->failures();
        $this->table(['player', 'car', 'day without', 'day with', 'allowed'], array_map(fn ($row): array => [$row->player, $row->rung, $row->target, $row->day ?? '-', sprintf('%.0f%%', $result['gate']->tolerance * 100)], $failures));
        $failures === []
            ? $this->info(sprintf('The streak moves no car by more than %.0f%% of its day (and a day).', $result['gate']->tolerance * 100))
            : $this->warn(sprintf('The streak moves %d purchases by more than %.0f%% of the day.', count($failures), $result['gate']->tolerance * 100));

        return $failures !== [];
    }

    private function percent(?float $share): string
    {
        return $share === null ? '-' : sprintf('%.1f%%', $share * 100);
    }
}
