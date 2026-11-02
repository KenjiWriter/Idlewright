<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Services\MissionBudget;
use Illuminate\Console\Command;

/**
 * What the missions of the game ask and pay, for a car on a track: for every template the goal it asks, what it is worth (in tanks of the car) and what it pays in cash and
 * XP, what a set pays on average (a set draws one template of every slot, by weight), what a month of doing everything comes to, and the coins of a month next to the
 * prices of the game. The tool for tuning the numbers of the rules, and for seeing what a change does before it is published. With `--strict` it is a gate: it fails when a
 * month of the sets is outside the share of the cash or the XP of 30 tanks that `ride.missions.budget` allows, which stops a publication; `--json` gives the same numbers to
 * the panel. The numbers are `MissionBudget`'s, and the panel's too.
 */
final class MissionsBudget extends Command
{
    use RunsInAGame;

    protected $signature = 'missions:budget
        {--car=rdzak-gt-4 : The slug of the car whose tank is the measure}
        {--track=zlomowo-street : The slug of the track it drives}
        {--level= : The level of the driver, which decides what templates fit (1, or the level of the limits with --strict)}
        {--strict : Fail when a month of the sets is outside the limits of the configuration}
        {--json : Print the numbers as JSON}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Show what the missions ask and pay, for a car on a track';

    public function handle(MissionBudget $budget): int
    {
        $strict = (bool) $this->option('strict');
        $level = $this->option('level') !== null ? (int) $this->option('level') : ($strict ? (int) config('ride.missions.budget.level', 12) : 1);
        $report = $budget->report((string) $this->option('car'), (string) $this->option('track'), $level);

        if ($report === null) {
            if ($strict) {
                $this->info('The rules of this game have no missions: there is nothing to judge.');

                return self::SUCCESS;
            }

            if ($this->option('json')) {
                $this->line('null');

                return self::SUCCESS;
            }

            $this->error('The rules of this game have no missions.');

            return self::FAILURE;
        }

        $failed = array_values(array_filter($report['checks'], static fn (array $check): bool => ! $check['ok']));

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_THROW_ON_ERROR));

            return $strict && $failed !== [] ? self::FAILURE : self::SUCCESS;
        }

        // A gate judges the shares, which are the same for every car; a game that has not the reference car of the command is not failed for it.
        if ($report['tank'] === null && ! $strict) {
            $this->error('That car cannot drive that track on its tank.');

            return self::FAILURE;
        }

        if ($report['tank'] !== null) {
            $this->amounts($report);
        }

        $this->limits($report['checks']);

        return $strict && $failed !== [] ? self::FAILURE : self::SUCCESS;
    }

    /** @param  array<string, mixed>  $report */
    private function amounts(array $report): void
    {
        $tank = $report['tank'];
        $dollars = static fn (int|float $cents): string => '$'.number_format($cents / 100, 2);

        $this->info(sprintf('A tank of the %s on %s: %d laps, %s gross, %s after the fuel, %d XP; filling it costs %s. The missions are %s.', $report['car'], $report['track'], $tank['laps'], $dollars($tank['gross_cents']), $dollars($tank['net_cents']), $tank['xp'], $dollars($tank['fuel_cents']), $report['enabled'] ? 'on' : 'OFF'));

        foreach ($report['periods'] as $period => $row) {
            $rows = array_map(static fn (array $template): array => [
                $template['slot'],
                $template['template'],
                $template['fits'] ? ($template['money'] ? $template['objective'].' $'.number_format($template['asks'] / 100, 0) : $template['objective'].' '.$template['asks']) : '(does not fit)',
                $template['weight'],
                number_format($template['worth'], 2).' tanks',
                $dollars($template['cash_cents']),
                $template['xp'],
                $template['items'],
            ], $row['templates']);

            $this->newLine();
            $this->line(sprintf('%s: %d missions, on average worth %.1f tanks of work, and the set pays about %s and %d XP, and %d coins for the whole set.', strtoupper($period), $row['missions'], $row['worth'], $dollars($row['cash_cents']), (int) round($row['xp']), $row['coins']));
            $this->table(['slot', 'template', 'goal', 'weight', 'worth', 'cash', 'XP', 'items'], $rows);
        }

        $main = $report['main'];

        $this->newLine();
        $this->line(sprintf('The main missions (%d) pay %d%% of what they are worth in cash and %d%% in XP: %.1f tanks of work in all, %s and %d XP.', $main['missions'], $main['cash_ppm'] / 10_000, $main['xp_ppm'] / 10_000, $main['worth'], $dollars($main['cash_cents']), $main['xp']));

        $month = $report['month'];

        $this->newLine();
        $this->line(sprintf('A month of doing everything: %s (%d%% of the %s that 30 tanks earn), %d XP (%d%% of the %d of 30 tanks), %d coins.', $dollars($month['cash_cents']), $month['cash_percent'], $dollars($month['cash_of_tanks_cents']), (int) round($month['xp']), $month['xp_percent'], $month['xp_of_tanks'], (int) round($month['coins'])));

        if ($report['vip'] !== null) {
            $this->line(sprintf('The VIP account costs %d coins: a month of doing everything is %d%% of it.', $report['vip']['coins'], $report['vip']['month_percent']));
        }

        if ($report['pack'] !== null) {
            $pack = $report['pack'];

            $this->line(sprintf('At the price of the smallest pack (%d coins for %.2f %s) a month of doing everything is worth %.2f %s; speeding up at %d minutes a coin, it is %.1f hours of timers.', $pack['coins'], $pack['price_minor'] / 100, $pack['currency'], $pack['worth'], $pack['currency'], $pack['minutes_per_coin'], $pack['speedup_hours']));
        }
    }

    /**
     * The limits and where the numbers stand against them.
     *
     * @param  list<array{name: string, value: float, min: float, max: float, ok: bool}>  $checks
     */
    private function limits(array $checks): void
    {
        $this->newLine();

        foreach ($checks as $check) {
            $line = sprintf('%s %s: %s (limits %s to %s)', $check['ok'] ? 'ok  ' : 'FAIL', $check['name'], round($check['value'], 4), $check['min'], $check['max']);

            $check['ok'] ? $this->line($line) : $this->error($line);
        }
    }
}
