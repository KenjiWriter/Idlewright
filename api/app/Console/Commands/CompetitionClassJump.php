<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\CarPerformance;
use App\Content\PartSets;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Domain\Run\RunFreezer;
use App\Domain\Run\RunInputs;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * What moving a car up a class does to what it earns: a car through the sets of parts of the shop, on some tracks, with the bots as they are and with the step of the bots by
 * class (the one of the rules, or one to try with `--step`). The jumps where the car earns less in the new class are marked, because they are the ones that a driver would not
 * make: they are what the step is for, and what a number of the step that is too big would make too many of.
 */
final class CompetitionClassJump extends Command
{
    protected $signature = 'competition:class-jump
        {--car=rdzak-gt-4 : The car to try}
        {--tracks=zlomowo-street,kruszyn-quarry,autodrom-circuit,wilcza-przelecz-switchbacks : The tracks, by slug, separated by commas}
        {--step= : A step of the bots by class to try, in ppm of their lap time (the rules have bots.class_step_ppm)}
        {--json : Print the report as JSON}';

    protected $description = 'What a car earns through the sets of parts, with the bots as they are and with the step of the bots by class';

    public function handle(RunInputsFactory $factory, Rules $rules, CarPerformance $performance, PartSets $sets): int
    {
        if (! $rules->competitionOn()) {
            $this->error('The rules of this game have no competition, so cars have no class.');

            return self::FAILURE;
        }

        $step = $this->option('step') === null ? $rules->botClassStepPpm() : (int) $this->option('step');
        $car = (string) $this->option('car');
        $tracks = array_filter(array_map('trim', explode(',', (string) $this->option('tracks'))));
        $price = $rules->fuelPriceCentsPerLiter();
        $freezer = new RunFreezer;
        $without = $factory->withClassStep(0);
        $with = $factory->withClassStep($step);
        $report = ['car' => $car, 'step_ppm' => $step, 'tracks' => []];

        try {
            foreach ($tracks as $track) {
                $tires = $rules->trackReferenceTires($track);
                $tiresSlot = $rules->model()->slotOf('tires');
                $previous = null;
                $rows = [];

                foreach ($sets->all() as $name => $mounted) {
                    $mountedHere = $tires !== null && $tiresSlot !== null ? [...$mounted, $tiresSlot => $tires] : $mounted;
                    $rating = $performance->ratingOf($car, $mounted);
                    $now = $this->earn($freezer, $without->make($car, $track, $mountedHere), $price);
                    $then = $this->earn($freezer, $with->make($car, $track, $mountedHere), $price);
                    $row = ['set' => $name, 'pi' => $rating['pi'], 'class' => $rating['class'], 'now' => $now, 'with_step' => $then, 'jump' => false, 'jump_pays' => null];

                    if ($previous !== null && $previous['class'] !== $row['class']) {
                        $row['jump'] = true;
                        $row['jump_pays'] = $then['net'] >= $previous['with_step']['net'];
                    }

                    $rows[] = $row;
                    $previous = $row;
                }

                $report['tracks'][$track] = $rows;
            }
        } catch (InvalidArgumentException $problem) {
            $this->error($problem->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("{$car}: net \$/h through the sets of parts, with the bots as they are and with a step of ".number_format($step / 10_000, 1).'% of the lap per class. * marks a jump of class after which the car earns less.');

        foreach ($report['tracks'] as $track => $rows) {
            $this->line('');
            $this->line($track);
            $this->table(
                ['set', 'PI', 'class', 'place', '$/h', 'place with step', '$/h with step', ''],
                array_map(static fn (array $row): array => [$row['set'], $row['pi'], $row['class'], 'P'.$row['now']['place'], number_format($row['now']['net']), 'P'.$row['with_step']['place'], number_format($row['with_step']['net']), $row['jump'] ? ($row['jump_pays'] ? 'jump' : 'jump *') : ''], $rows),
            );
        }

        return self::SUCCESS;
    }

    /**
     * The place among the bots and the net earnings of an hour in dry weather (the reward of the laps less the fuel they burn), in dollars.
     *
     * @return array{place: int, net: float}
     */
    private function earn(RunFreezer $freezer, RunInputs $inputs, int $priceCentsPerLiter): array
    {
        $config = $freezer->freeze($inputs, 'class-jump', 0)->config;
        $lapMs = $config['lap_ms']['dry'];
        $perLap = $config['lap_cash_cents']['dry'] - $config['fuel_per_lap_ml'] * $priceCentsPerLiter / 1000;

        return ['place' => (int) ($config['field']['position'] ?? 0), 'net' => round(3_600_000 / $lapMs * $perLap / 100, 1)];
    }
}
