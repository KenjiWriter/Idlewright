<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\CarPerformance;
use App\Content\Catalog;
use App\Content\PartSets;
use App\Content\Rules;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Shows what the classes of the competition hold: the performance index of every combustion car of the pack as it comes, with the strongest parts of the shop of each
 * tier on it (all the parts of a tier or lower, at the common rarity), and with the best parts at a rare +4, an epic +8 and a legendary +12, and how many cars each
 * class gets in each case. This is what the limits of the classes are decided from; the fit of the parts to the car (the layout of the engine, the booster for an
 * intercooler) is not checked, so the columns with parts are an upper bound.
 */
final class CompetitionPiReport extends Command
{
    protected $signature = 'competition:pi-report
        {--json : Print the report as JSON}';

    protected $description = 'The performance index of the cars of the pack (stock and with the best parts of the shop) against the classes of the competition';

    public function handle(Catalog $catalog, Rules $rules, CarPerformance $performance): int
    {
        if ($rules->competition() === null) {
            $this->error('The rules of this game have no competition.');

            return self::FAILURE;
        }

        $classes = $rules->performanceClasses();
        $sets = (new PartSets($catalog, $rules))->all();
        $counts = array_fill_keys(array_keys($sets), []);

        foreach ($catalog->vehicles() as $slug => $vehicle) {
            if (($vehicle['powertrain'] ?? null) !== 'ice') {
                continue;
            }

            $row = ['car' => $slug, 'tier' => (int) $vehicle['tier'], 'pi' => []];

            try {
                foreach ($sets as $name => $mounted) {
                    $pi = $performance->of($slug, $mounted);
                    $row['pi'][$name] = $pi;
                    $class = $classes->of($pi);
                    $counts[$name][$class] = ($counts[$name][$class] ?? 0) + 1;
                }
            } catch (InvalidArgumentException $problem) {
                $this->warn("{$slug}: {$problem->getMessage()}");

                continue;
            }

            $row['class'] = $classes->of($row['pi']['stock']);
            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => [$a['pi']['stock'], $a['car']] <=> [$b['pi']['stock'], $b['car']]);

        $report = [
            'reference' => ['car' => $rules->competition()['pi']['reference_car'], 'pi' => $rules->competition()['pi']['reference_pi']],
            'ceiling_pi' => $performance->ceiling(),
            'max_pi' => $rules->competition()['pi']['max_pi'],
            'classes' => array_map(static fn (string $class): array => ['class' => $class, ...$classes->bounds($class)], $classes->names()),
            'sets' => array_keys($sets),
            'cars_per_class' => $counts,
            'cars' => $rows,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Reference: {$report['reference']['car']} = {$report['reference']['pi']} PI. Every statistic at the ceiling of the game: {$report['ceiling_pi']} PI (a PI is never shown above {$report['max_pi']}).");
        $this->table(
            ['car', 'tier', 'class', ...array_keys($sets)],
            array_map(static fn (array $row): array => [$row['car'], $row['tier'], $row['class'], ...array_values($row['pi'])], $rows),
        );
        $this->table(
            ['class', 'from PI', 'to PI', ...array_keys($sets)],
            array_map(static fn (array $bounds): array => [$bounds['class'], $bounds['min'], $bounds['max'] ?? 'no limit', ...array_map(static fn (array $perClass): int => $perClass[$bounds['class']] ?? 0, array_values($counts))], $report['classes']),
        );

        return self::SUCCESS;
    }
}
