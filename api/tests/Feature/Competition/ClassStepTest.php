<?php

declare(strict_types=1);

use App\Content\BotProfiles;
use App\Content\Catalog;
use App\Content\PackValidator;
use App\Content\PartSets;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Domain\Run\RunFreezer;
use Illuminate\Support\Facades\Artisan;

// The step of the bots by class (`bots.class_step_ppm`): the bots of a tier get quicker by a part of their lap for every class that a car is above the native class of the tier
// (the highest class of a stock car of that tier). It is off at zero, and it never touches a stock car.

/** The lap times of the bots that a car meets on a track, with a step of the bots by class (null leaves the rules in charge). */
function botsOf(string $car, string $track, array $mounted = [], ?int $step = null): array
{
    return app(RunInputsFactory::class)->withClassStep($step)->make($car, $track, $mounted)->field->rivalsMs;
}

/** @return list<string> the place of every error the validator finds once a change is made to the rules */
function botStepErrors(Closure $change): array
{
    return array_map(static fn (array $issue): string => "{$issue['file']} {$issue['pointer']}", PackValidator::fromConfig()->validateSource(contentWith(['rules/starter' => $change]))->errors());
}

describe('the step of the bots by class', function () {
    it('is off in the rules that ship: the bots are the ones of the tier', function () {
        expect(Rules::fromSource(repositoryContent())->botClassStepPpm())->toBe(0)
            ->and(botsOf('rdzak-gt-4', 'kruszyn-quarry', [], 0))->toBe(botsOf('rdzak-gt-4', 'kruszyn-quarry'));
    });

    it('never touches a stock car: every car of the pack meets the same bots with a step of 8% as without one', function () {
        foreach (['zlomowo-street', 'kruszyn-quarry', 'autodrom-circuit'] as $track) {
            foreach (array_keys(Catalog::fromSource(repositoryContent())->vehicles()) as $car) {
                if ((Catalog::fromSource(repositoryContent())->vehicle($car)['powertrain'] ?? null) !== 'ice') {
                    continue;
                }

                expect(botsOf($car, $track, [], 80_000))->toBe(botsOf($car, $track, [], 0), "{$car} on {$track}");
            }
        }
    });

    it('makes the bots quicker by a step for every class the car is above the native class of its tier', function () {
        $sets = (new PartSets(app(Catalog::class), app(Rules::class)))->all();
        $plain = botsOf('rdzak-gt-4', 'kruszyn-quarry');
        $oneUp = botsOf('rdzak-gt-4', 'kruszyn-quarry', $sets['T5'], 80_000);
        $twoUp = botsOf('rdzak-gt-4', 'kruszyn-quarry', $sets['T9'], 80_000);
        $sameClass = botsOf('rdzak-gt-4', 'kruszyn-quarry', $sets['T4'], 80_000);

        // The starter car is of the tier whose cars are in class D at most; the parts of T5 make it a C and those of T9 a B.
        expect($sameClass)->toBe($plain);

        foreach ($plain as $index => $ms) {
            expect($oneUp[$index] / $ms)->toBeBetween(0.919, 0.921)
                ->and($twoUp[$index] / $ms)->toBeBetween(0.839, 0.841);
        }
    });

    it('costs the car a place when it moves up a class, and the place is frozen with the run', function () {
        $sets = (new PartSets(app(Catalog::class), app(Rules::class)))->all();
        $factory = app(RunInputsFactory::class);
        $before = $factory->withClassStep(0)->make('rdzak-gt-4', 'zlomowo-street', $sets['T5']);
        $after = $factory->withClassStep(100_000)->make('rdzak-gt-4', 'zlomowo-street', $sets['T5']);
        $freezer = new RunFreezer;

        $placeBefore = $freezer->freeze($before, 'seed', 0)->config['field']['position'];
        $placeAfter = $freezer->freeze($after, 'seed', 0)->config['field']['position'];

        expect($placeBefore)->toBe(1)
            ->and($placeAfter)->toBe(3);
    });

    it('is read from the rules when the override says nothing, and does nothing in a game that has no competition', function () {
        $with = contentWith(['rules/starter' => static function (array $rules): array {
            $rules['bots']['class_step_ppm'] = 80_000;

            return $rules;
        }]);
        $without = contentWith(['rules/starter' => static function (array $rules): array {
            $rules['bots']['class_step_ppm'] = 80_000;
            unset($rules['competition']);

            return $rules;
        }]);
        $sets = (new PartSets(app(Catalog::class), app(Rules::class)))->all();
        $factory = static fn ($source): RunInputsFactory => new RunInputsFactory(Catalog::fromSource($source), Rules::fromSource($source), BotProfiles::fromSource($source));

        expect(Rules::fromSource($with)->botClassStepPpm())->toBe(80_000)
            ->and($factory($with)->make('rdzak-gt-4', 'kruszyn-quarry', $sets['T5'])->field->rivalsMs)->toBe(botsOf('rdzak-gt-4', 'kruszyn-quarry', $sets['T5'], 80_000))
            ->and($factory($without)->make('rdzak-gt-4', 'kruszyn-quarry', $sets['T5'])->field->rivalsMs)->toBe(botsOf('rdzak-gt-4', 'kruszyn-quarry', $sets['T5'], 0));
    });
});

describe('the rules of the step', function () {
    it('are valid at any step that leaves the bots a lap time, and warn when the game has no competition', function () {
        expect(botStepErrors(static function (array $rules): array {
            $rules['bots']['class_step_ppm'] = 80_000;

            return $rules;
        }))->toBe([]);

        $warnings = PackValidator::fromConfig()->validateSource(contentWith(['rules/starter' => static function (array $rules): array {
            $rules['bots']['class_step_ppm'] = 80_000;
            $rules['competition']['enabled'] = false;

            return $rules;
        }]))->warnings();

        expect(array_map(static fn (array $issue): string => $issue['pointer'], $warnings))->toContain('/bots/class_step_ppm');
    });

    it('refuse steps that together take the lap time of the bots away, and a step over the limit of the schema', function () {
        expect(botStepErrors(static function (array $rules): array {
            $rules['bots']['class_step_ppm'] = 300_000;

            return $rules;
        }))->toBe(['rules/starter.json /bots/class_step_ppm'])
            ->and(botStepErrors(static function (array $rules): array {
                $rules['bots']['class_step_ppm'] = 400_000;

                return $rules;
            }))->not->toBe([]);
    });
});

describe('the report of the jumps', function () {
    it('prints the car through the sets with and without the step, and marks the jumps that do not pay', function () {
        expect(Artisan::call('competition:class-jump', ['--step' => 100_000, '--tracks' => 'kruszyn-quarry', '--json' => true]))->toBe(0);

        $report = json_decode(Artisan::output(), true);
        $rows = collect($report['tracks']['kruszyn-quarry'])->keyBy('set');

        expect($report['step_ppm'])->toBe(100_000)
            ->and($rows['stock']['with_step'])->toBe($rows['stock']['now'])
            ->and($rows['T5']['class'])->toBe('C')
            ->and($rows['T5']['jump'])->toBeTrue()
            ->and($rows['T5']['jump_pays'])->toBeFalse()
            ->and($rows['T5']['with_step']['net'])->toBeLessThan($rows['T5']['now']['net']);
    });
});
