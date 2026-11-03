<?php

declare(strict_types=1);

use App\Content\Publishing\PackGates;
use App\Content\Rules;
use App\Services\TuningBudget;
use Illuminate\Support\Facades\Artisan;

// What the best set of the bonuses of the tuning is worth, for the reference cars on a track of every kind and surface: the report, the command, and the gate that stops a pack
// when the best set takes more off the lap than the owner approved. The worst case that was measured, the Bizon on gravel, is a little over the target of the design and is
// reported as a warning, not passed in silence.

/** What the report says of the worst case. @return array<string, mixed> */
function tuningBudgetReport(): array
{
    return app(TuningBudget::class)->report() ?? throw new RuntimeException('The rules have nothing to measure.');
}

describe('the report of the best set of bonuses', function () {
    it('measures both reference cars on a track of every kind and surface, worst first', function () {
        $report = tuningBudgetReport();
        $cars = array_values(array_unique(array_column($report['rows'], 'car')));
        $laps = array_column($report['rows'], 'lap_percent');

        expect($cars)->toEqualCanonicalizing(['rdzak-gt-4', 'bizon-m-2'])
            ->and(count($report['rows']))->toBeGreaterThanOrEqual(8)
            // The best set only ever makes the lap shorter, and the rows run from the largest drop.
            ->and(max($laps))->toBeLessThan(0.0)
            ->and($laps)->toBe(collect($laps)->sort()->values()->all());
    });

    it('finds the worst case where the bonuses help the most: the Bizon on gravel, about 11% off the lap, a little over the 10% of the design', function () {
        $report = tuningBudgetReport();

        expect($report['worst'])->toMatchArray(['car' => 'bizon-m-2', 'surface' => 'gravel'])
            ->and($report['worst']['lap_percent'])->toBeLessThan(-10.0)->and($report['worst']['lap_percent'])->toBeGreaterThan(-12.0)
            ->and($report['target'])->toBe(10.0)->and($report['approved'])->toBe(12.0)
            // It is over the target and under the ceiling: a warning says so, and the check passes.
            ->and($report['warnings'])->toHaveCount(1)->and($report['warnings'][0])->toContain('bizon-m-2')
            ->and($report['checks'][0])->toMatchArray(['ok' => true, 'max' => 12.0]);
    });

    it('does not say the income is wrong: it is reported, and carries the jump of the place among the bots', function () {
        $rows = collect(tuningBudgetReport()['rows']);

        // The Rdzak on the quarry goes up a place among the bots, so its income jumps well past what its lap would give.
        expect($rows->every(fn (array $row): bool => $row['income_percent'] > 0))->toBeTrue()
            ->and($rows->max('income_percent'))->toBeGreaterThan(20.0);
    });

    it('has no warning when the best set stays under the target, and fails the check when it goes over the approved ceiling', function () {
        config(['ride.tuning.budget.target_lap_percent' => 12.0, 'ride.tuning.budget.max_lap_percent' => 12.0]);
        expect(tuningBudgetReport()['warnings'])->toBe([]);

        config(['ride.tuning.budget.target_lap_percent' => 5.0, 'ride.tuning.budget.max_lap_percent' => 9.0]);
        $report = tuningBudgetReport();

        expect($report['checks'][0]['ok'])->toBeFalse()->and($report['warnings'])->toBe([]);
    });

    it('is nothing to judge for a game that has no bonuses', function () {
        $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
        unset($data['tuning']);
        app()->instance(Rules::class, Rules::fromArray($data));

        expect(app(TuningBudget::class)->report())->toBeNull();
    });
});

describe('the command tuning:budget', function () {
    it('prints the table and the warning, and passes with --strict while the worst case is under the approved ceiling', function () {
        expect(Artisan::call('tuning:budget', ['--strict' => true]))->toBe(0);

        $output = Artisan::output();

        expect($output)->toContain('bizon-m-2')->toContain('WARN')->toContain('worst lap drop of the best set');
    });

    it('fails with --strict when the worst case is over the approved ceiling, and still passes without it', function () {
        config(['ride.tuning.budget.max_lap_percent' => 9.0]);

        expect(Artisan::call('tuning:budget', ['--strict' => true]))->toBe(1)
            ->and(Artisan::output())->toContain('FAIL')
            ->and(Artisan::call('tuning:budget'))->toBe(0);
    });

    it('gives the same numbers as JSON for the panel', function () {
        Artisan::call('tuning:budget', ['--json' => true]);

        $json = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        expect($json['worst']['car'])->toBe('bizon-m-2')->and($json['rows'])->toHaveCount(count(tuningBudgetReport()['rows']));
    });

    it('is one of the gates of a publication', function () {
        $gates = (new ReflectionClass(PackGates::class))->getReflectionConstant('GATES')->getValue();

        expect(array_keys($gates))->toContain('tuning:budget --strict');
    });
});
