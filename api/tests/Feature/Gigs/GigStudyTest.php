<?php

declare(strict_types=1);

use App\Content\Catalog;
use App\Content\GameGigs;
use App\Content\GameIncome;
use App\Content\GigCatalog;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Domain\Economy\GameLadder;
use App\Domain\Economy\GigEconomy;
use App\Domain\Economy\GigResult;
use App\Domain\Economy\GigStudy;
use Illuminate\Support\Facades\Artisan;

// The gigs in the economy simulator: what a gig is worth to the main car at each level, and what the gigs do to the days on which a player buys the cars of the ladder.

/** The gigs of the game as the simulator sees them, from a document of the gigs that may be changed. @param  callable(array<string, mixed>): array<string, mixed>|null  $change */
function gigEconomy(?callable $change = null): GigEconomy
{
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/gigs.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = GigCatalog::fromArray($change === null ? $data : $change($data));
    $levels = app(GameLadder::class)->levels(economyRules());
    $economy = (new GameGigs(app(Rules::class), $catalog, app(Catalog::class), app(RunInputsFactory::class)))->economy(app(GameIncome::class)->byLevel($levels));

    expect($economy)->not->toBeNull();

    return $economy;
}

function gigsStudied(GigEconomy $gigs, array $only = []): GigResult
{
    return app(GigStudy::class)->run(economyRules(), $gigs, $only, [1, 3, 27]);
}

describe('what a gig is worth to the main car', function () {
    it('grows with the level, takes no more than the longest gig of the band, and is a part of the offers on the board', function () {
        $gigs = gigEconomy();
        $low = $gigs->perGig(3);
        $high = $gigs->perGig(27);

        // At level 3 the longest gig is 27 minutes of the reference time; the main car of the level is a racing hatchback, which takes the taxi and the parcels.
        expect($low['cash'])->toBeGreaterThan(0.0)
            ->and($low['hours'])->toBeLessThanOrEqual(0.45)
            ->and($low['share'])->toBeGreaterThan(0.0)->toBeLessThan(1.0)
            ->and($high['cash'])->toBeGreaterThan($low['cash'])
            ->and($gigs->dailyLimit(3))->toBe(6)
            ->and($gigs->dailyLimit(27))->toBe(10);
    });

    it('is nothing when no template can be taken by the car of the level', function () {
        $gigs = gigEconomy(function (array $data): array {
            foreach ($data['templates'] as $key => $template) {
                $data['templates'][$key]['class_in'] = ['van'];
            }

            return $data;
        });

        expect($gigs->perGig(3))->toBeNull();
    });
});

describe('the gigs on top of the scenario game', function () {
    it('plays every kind of player in every plan, and moves no day of the ladder by more than the tolerance with a gig on every visit or with every car on a gig', function () {
        $result = gigsStudied(gigEconomy());

        expect(array_keys($result->baseline))->toBe(['A', 'B', 'C'])
            ->and(array_keys($result->plans))->toBe(['usual', 'every-visit', 'every-car', 'daily-limit'])
            ->and($result->plans['every-visit']['binding'])->toBeTrue()
            ->and($result->plans['daily-limit']['binding'])->toBeFalse()
            ->and($result->plans['every-car']['runs']['A']->notes['gigs']['gigs'])->toBeGreaterThan($result->plans['every-visit']['runs']['A']->notes['gigs']['gigs'])
            ->and($result->passed())->toBeTrue()
            ->and($result->worth)->not->toBeEmpty();
    });

    it('buys the cars sooner the more gigs are taken, never later', function () {
        $result = gigsStudied(gigEconomy(), ['A']);
        $day = static fn (string $plan): int => $result->plans[$plan]['runs']['A']->dayOf(8);

        expect($day('usual'))->toBeLessThanOrEqual($result->baseline['A']->dayOf(8))
            ->and($day('every-visit'))->toBeLessThanOrEqual($day('usual'))
            ->and($day('daily-limit'))->toBeLessThan($day('every-visit'));
    });

    it('fails the gate when the gigs pay far more than the laps', function () {
        $generous = gigEconomy(function (array $data): array {
            foreach ($data['bands'] as $index => $band) {
                $data['bands'][$index]['ratio_ppm'] = 30_000_000;
            }

            return $data;
        });

        expect(gigsStudied($generous)->passed())->toBeFalse();
    });

    it('plays only the kinds of players it is asked for', function () {
        $result = gigsStudied(gigEconomy(), ['B']);

        expect(array_keys($result->baseline))->toBe(['B'])->and(array_keys($result->plans['usual']['runs']))->toBe(['B']);
    });
});

describe('the simulator command', function () {
    it('adds the gigs to the report with --gigs, with what a gig is worth at the levels and the gate of each plan', function () {
        Artisan::call('economy:simulate', ['--gigs' => true, '--json' => true]);
        $document = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        expect($document['gigs']['passed'])->toBeTrue()
            ->and(array_keys($document['gigs']['plans']))->toBe(['usual', 'every-visit', 'every-car', 'daily-limit'])
            ->and($document['gigs']['worth'][0]['level'])->toBe(1)
            ->and($document['gigs']['plans']['every-visit']['players']['A'])->toHaveKeys(['gigs', 'uplift_cash_30d']);

        Artisan::call('economy:simulate', ['--json' => true]);

        expect(json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR))->not->toHaveKey('gigs');
    });
});
