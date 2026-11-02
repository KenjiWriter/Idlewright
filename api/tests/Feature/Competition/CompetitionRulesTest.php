<?php

declare(strict_types=1);

use App\Content\CarPerformance;
use App\Content\PackValidator;
use App\Content\Rules;
use App\Domain\Competition\LuckTable;
use App\Domain\Settlement\RatingRules;
use Illuminate\Support\Facades\Artisan;

// The competition as content: the numbers of the rules, what the validator says of them, the performance index of the cars of the pack, and the commands.

/** @return list<string> the place of every error the validator finds in the rules once a change is made to them */
function competitionErrors(Closure $change): array
{
    $content = contentWith(['rules/starter' => $change]);

    return array_map(static fn (array $issue): string => "{$issue['file']} {$issue['pointer']}", PackValidator::fromConfig()->validateSource($content)->errors());
}

describe('the rules of the competition', function () {
    it('are valid in the pack that ships', function () {
        expect(PackValidator::fromConfig()->validateSource(repositoryContent())->errors())->toBe([]);
    });

    it('are read by the rules of the game: the switch, the PI and its classes, the numbers of Respekt and of a match', function () {
        $rules = Rules::fromSource(repositoryContent());
        $competition = $rules->competition();

        expect($rules->competitionOn())->toBeTrue()
            ->and($competition['pi']['reference_car'])->toBe('rdzak-gt-4')
            ->and($competition['pi']['reference_pi'])->toBe(300)
            ->and(array_sum($competition['pi']['weights_ppm']))->toBe(1_000_000)
            ->and($competition['match'])->toBe(['luck_sigma_ppm' => 20_000, 'runs' => 3])
            ->and($competition['pi']['max_pi'])->toBe(999)
            ->and($rules->performanceClasses()->names())->toBe(['F', 'E', 'D', 'C', 'B', 'A', 'S', 'SS'])
            ->and($rules->performanceClasses()->of(0))->toBe('F')
            ->and($rules->performanceClasses()->of(399))->toBe('D')
            ->and($rules->performanceClasses()->of(400))->toBe('C')
            ->and($rules->performanceClasses()->of(999))->toBe('SS');
    });

    it('give Respekt the numbers it always had, so that the first game plays as before', function () {
        expect(Rules::fromSource(repositoryContent())->ratingRules())->toEqual(RatingRules::defaults());
    });

    it('are off in a game whose rules do not mention them, and Respekt then has the numbers of the first game', function () {
        $rules = Rules::fromSource(contentWith(['rules/starter' => static function (array $rules): array {
            unset($rules['competition']);

            return $rules;
        }]));

        expect($rules->competition())->toBeNull()
            ->and($rules->competitionOn())->toBeFalse()
            ->and($rules->ratingRules())->toEqual(RatingRules::defaults());
    });

    it('refuse a reference car that is not in the pack, weights that do not add up or are for a statistic the game does not have', function () {
        expect(competitionErrors(static function (array $rules): array {
            $rules['competition']['pi']['reference_car'] = 'no-such-car';

            return $rules;
        }))->toBe(['rules/starter.json /competition/pi/reference_car'])
            ->and(competitionErrors(static function (array $rules): array {
                $rules['competition']['pi']['weights_ppm']['accel'] = 300_000;

                return $rules;
            }))->toBe(['rules/starter.json /competition/pi/weights_ppm'])
            ->and(competitionErrors(static function (array $rules): array {
                $rules['competition']['pi']['weights_ppm']['charm'] = 0;

                return $rules;
            }))->toBe(['rules/starter.json /competition/pi/weights_ppm/charm']);
    });

    it('refuse limits of the classes that do not grow, and a top class that is also a class with a limit', function () {
        expect(competitionErrors(static function (array $rules): array {
            $rules['competition']['pi']['class_max_pi']['B'] = 300;

            return $rules;
        }))->toBe(['rules/starter.json /competition/pi/class_max_pi/B'])
            ->and(competitionErrors(static function (array $rules): array {
                $rules['competition']['pi']['top_class'] = 'S';

                return $rules;
            }))->toBe(['rules/starter.json /competition/pi/top_class']);
    });

    it('refuse a highest PI that is not above the limit of the last class with a limit', function () {
        expect(competitionErrors(static function (array $rules): array {
            $rules['competition']['pi']['max_pi'] = 700;

            return $rules;
        }))->toBe(['rules/starter.json /competition/pi/max_pi']);
    });

    it('refuse a minimum Respekt above the start, divisions that do not begin one after the other, and a match of an even number of runs', function () {
        expect(competitionErrors(static function (array $rules): array {
            $rules['competition']['rating']['minimum'] = 1_200;

            return $rules;
        }))->toBe(['rules/starter.json /competition/rating/minimum'])
            ->and(competitionErrors(static function (array $rules): array {
                $rules['competition']['rating']['regional_from'] = 1_100;

                return $rules;
            }))->toBe(['rules/starter.json /competition/rating'])
            ->and(competitionErrors(static function (array $rules): array {
                $rules['competition']['match']['runs'] = 2;

                return $rules;
            }))->toBe(['rules/starter.json /competition/match/runs']);
    });
});

describe('the performance index of the cars of the pack', function () {
    it('gives the reference car exactly the reference PI, and the stock cars the classes of the table of the design', function () {
        $performance = app(CarPerformance::class);

        expect($performance->of('rdzak-gt-4'))->toBe(300)
            ->and($performance->classOf(300))->toBe('D')
            ->and($performance->of('furgon-z-1'))->toBeBetween(230, 240)
            ->and($performance->classOf($performance->of('furgon-z-1')))->toBe('E')
            ->and($performance->of('serwal-gt'))->toBeBetween(365, 380)
            ->and($performance->of('orzel-v12'))->toBeBetween(455, 465)
            ->and($performance->classOf($performance->of('orzel-v12')))->toBe('C')
            ->and($performance->maxPi())->toBe(999);
    });

    it('grows with the parts on the car, and does not count a lap time: the same car on any track has the same PI', function () {
        $performance = app(CarPerformance::class);
        $stock = $performance->of('rdzak-gt-4');
        $tuned = $performance->of('rdzak-gt-4', ['tires' => ['slug' => 'slick-tires-t7', 'rarity' => 1, 'upgrade' => 0]]);
        $legendary = $performance->of('rdzak-gt-4', ['tires' => ['slug' => 'slick-tires-t7', 'rarity' => 5, 'upgrade' => 12]]);

        expect($tuned)->toBeGreaterThan($stock)
            ->and($legendary)->toBeGreaterThan($tuned)
            ->and($performance->ceiling())->toBeBetween(900, 960);
    });

    it('does not depend on the overdue service of a car, which comes and goes, but on the wear of its parts', function () {
        $performance = app(CarPerformance::class);
        $worn = $performance->of('rdzak-gt-4', ['tires' => ['slug' => 'street-tires-t2', 'rarity' => 1, 'upgrade' => 0, 'wear' => 800_000_000]]);
        $fresh = $performance->of('rdzak-gt-4', ['tires' => ['slug' => 'street-tires-t2', 'rarity' => 1, 'upgrade' => 0]]);

        expect($worn)->toBeLessThan($fresh);
    });
});

describe('the commands', function () {
    it('keep the luck table up to date: --check passes on the file that ships', function () {
        expect(Artisan::call('content:luck-table', ['--check' => true]))->toBe(0)
            ->and(LuckTable::fromFile(config('ride.content_path').'/tables/luck-ppm.json')->luckPpm(999_999, 20_000))->toBeGreaterThan(60_000);
    });

    it('print the report of the PI of the cars against the classes', function () {
        expect(Artisan::call('competition:pi-report', ['--json' => true]))->toBe(0);

        $report = json_decode(Artisan::output(), true);

        expect($report['reference'])->toBe(['car' => 'rdzak-gt-4', 'pi' => 300])
            ->and($report['ceiling_pi'])->toBeBetween(900, 960)
            ->and(array_column($report['classes'], 'class'))->toBe(['F', 'E', 'D', 'C', 'B', 'A', 'S', 'SS'])
            ->and($report['sets'])->toBe(['stock', 'T1', 'T2', 'T3', 'T4', 'T5', 'T7', 'T9', 'rare+4', 'epic+8', 'best+12'])
            ->and(count($report['cars']))->toBe(23)
            ->and(array_sum($report['cars_per_class']['stock']))->toBe(23);
    });
});
