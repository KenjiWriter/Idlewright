<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\CarPerformance;
use App\Content\Catalog;
use App\Content\Rules;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Shows what the classes of the competition hold: the performance index of every combustion car of the pack as it comes, with the strongest parts of the shop on
 * it at the common rarity and at the legendary one with the best upgrade, and how many cars each class gets in each case. This is what the limits of the classes
 * are decided from; the fit of the parts to the car (the layout of the engine, the booster for an intercooler) is not checked, so the last columns are an upper bound.
 */
final class CompetitionPiReport extends Command
{
    private const LEGENDARY = 5;

    private const BEST_UPGRADE = 12;

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
        $slots = $rules->model()->slotKeys();
        $best = $this->bestShopParts($catalog, $rules, $slots);
        $rows = [];
        $counts = ['stock' => [], 'common' => [], 'legendary' => []];

        foreach ($catalog->vehicles() as $slug => $vehicle) {
            if (($vehicle['powertrain'] ?? null) !== 'ice') {
                continue;
            }

            try {
                $stock = $performance->of($slug);
                $common = $performance->of($slug, $this->mounted($best, 1, 0));
                $legendary = $performance->of($slug, $this->mounted($best, self::LEGENDARY, self::BEST_UPGRADE));
            } catch (InvalidArgumentException $problem) {
                $this->warn("{$slug}: {$problem->getMessage()}");

                continue;
            }

            foreach (['stock' => $stock, 'common' => $common, 'legendary' => $legendary] as $case => $pi) {
                $class = $classes->of($pi);
                $counts[$case][$class] = ($counts[$case][$class] ?? 0) + 1;
            }

            $rows[] = ['car' => $slug, 'tier' => (int) $vehicle['tier'], 'stock' => $stock, 'class' => $classes->of($stock), 'common' => $common, 'legendary' => $legendary];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['stock'], $a['car']] <=> [$b['stock'], $b['car']]);

        $report = [
            'reference' => ['car' => $rules->competition()['pi']['reference_car'], 'pi' => $rules->competition()['pi']['reference_pi']],
            'ceiling_pi' => $performance->ceiling(),
            'classes' => array_map(static fn (string $class): array => ['class' => $class, ...$classes->bounds($class)], $classes->names()),
            'best_shop_parts' => array_map(static fn (string $slug): string => $slug, $best),
            'cars_per_class' => $counts,
            'cars' => $rows,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Reference: {$report['reference']['car']} = {$report['reference']['pi']} PI. Every statistic at the ceiling of the game: {$report['ceiling_pi']} PI.");
        $this->table(['car', 'tier', 'stock PI', 'class', 'best parts, common', 'best parts, legendary +'.self::BEST_UPGRADE], array_map(static fn (array $row): array => [$row['car'], $row['tier'], $row['stock'], $row['class'], $row['common'], $row['legendary']], $rows));
        $this->table(
            ['class', 'from PI', 'to PI', 'cars, stock', 'cars, best parts common', 'cars, best parts legendary'],
            array_map(static fn (array $bounds) => [$bounds['class'], $bounds['min'], $bounds['max'] ?? 'no limit', $counts['stock'][$bounds['class']] ?? 0, $counts['common'][$bounds['class']] ?? 0, $counts['legendary'][$bounds['class']] ?? 0], $report['classes']),
        );

        return self::SUCCESS;
    }

    /**
     * The strongest part of the shop in every slot: the one of the highest tier (the first on the shelf among equals).
     *
     * @param  list<string>  $slots
     * @return array<string, string> the slug of the part by slot
     */
    private function bestShopParts(Catalog $catalog, Rules $rules, array $slots): array
    {
        $best = [];

        foreach ($rules->shopParts() as $slug) {
            $part = $catalog->part($slug);

            if ($part === null || ! in_array($part['slot'], $slots, true)) {
                continue;
            }

            if (! isset($best[$part['slot']]) || $part['tier'] > $catalog->part($best[$part['slot']])['tier']) {
                $best[$part['slot']] = $slug;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, string>  $best
     * @return array<string, array{slug: string, rarity: int, upgrade: int}>
     */
    private function mounted(array $best, int $rarity, int $upgrade): array
    {
        return array_map(static fn (string $slug): array => ['slug' => $slug, 'rarity' => $rarity, 'upgrade' => $upgrade], $best);
    }
}
