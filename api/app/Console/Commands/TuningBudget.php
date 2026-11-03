<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Services\TuningBudget as Budget;
use Illuminate\Console\Command;

/**
 * What a full set of the bonuses of the tuning is worth: for the reference cars on a track of every kind and surface, how much the best bonuses of the rules, each at the most a
 * try can give, take off the lap and add to the income of the shop set. The tool for tuning the limits of the bonuses, and for seeing what a change does before it is published. With
 * `--strict` it is a gate: it fails when the worst case is above the ceiling that was approved (`ride.tuning.budget.max_lap_percent`), and a case over the target of the design
 * but under the ceiling is printed as a warning, so it is never passed in silence. `--json` gives the same numbers to the panel. The numbers are `TuningBudget`'s, and the panel's too.
 */
final class TuningBudget extends Command
{
    use RunsInAGame;

    protected $signature = 'tuning:budget
        {--strict : Fail when the worst case of the best set is above the approved ceiling}
        {--json : Print the numbers as JSON}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Show what a full set of the bonuses of the tuning is worth, car by car and track by track';

    public function handle(Budget $budget): int
    {
        $strict = (bool) $this->option('strict');
        $report = $budget->report();

        if ($report === null) {
            if ($this->option('json')) {
                $this->line('null');

                return self::SUCCESS;
            }

            $strict ? $this->info('The rules of this game have no bonuses of the tuning that can be measured: there is nothing to judge.') : $this->error('The rules of this game have no bonuses of the tuning that can be measured.');

            return $strict ? self::SUCCESS : self::FAILURE;
        }

        $failed = array_filter($report['checks'], static fn (array $check): bool => ! $check['ok']);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_THROW_ON_ERROR));

            return $strict && $failed !== [] ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['car', 'track', 'kind', 'surface', 'lap', 'best lap', 'lap %', 'income %', 'try (min of income)', 'h to 8%', 'h to 10%'], array_map(static fn (array $row): array => [
            $row['car'], $row['track'], $row['kind'], $row['surface'],
            number_format($row['lap_ms'] / 1000, 1).' s', number_format($row['best_lap_ms'] / 1000, 1).' s',
            sprintf('%+.1f', $row['lap_percent']), sprintf('%+.1f', $row['income_percent']),
            number_format($row['try_minutes'], 1), $row['hours_to_8'] ?? '-', $row['hours_to_10'] ?? '-',
        ], $report['rows']));

        $this->line(sprintf('A bonus takes %s tries on average to reach 8%% and %s to reach 10%%; the hours are those of the income of the car (a try is a share of the price of an average part of the set).', $report['tries']['to_8'] ?? '-', $report['tries']['to_10'] ?? '-'));

        foreach ($report['warnings'] as $warning) {
            $this->warn('WARN '.$warning);
        }

        foreach ($report['checks'] as $check) {
            $line = sprintf('%s %s: %s (limits %s to %s)', $check['ok'] ? 'ok  ' : 'FAIL', $check['name'], $check['value'], $check['min'], $check['max']);

            $check['ok'] ? $this->line($line) : $this->error($line);
        }

        return $strict && $failed !== [] ? self::FAILURE : self::SUCCESS;
    }
}
