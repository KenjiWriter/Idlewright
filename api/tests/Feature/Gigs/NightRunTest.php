<?php

declare(strict_types=1);

use App\Content\ContentSource;
use App\Content\GigCatalog;
use App\Content\PackValidator;
use App\Content\Rules;
use App\Domain\Gigs\NightOdds;
use App\Models\CityGarage;
use App\Models\Gig;
use App\Models\LedgerEntry;
use App\Models\TutorialProgress;
use App\Models\User;
use App\Models\Wallet;
use App\Services\GigPolice;
use App\Services\Ledger;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

// The night run: one opportunity for each night of the game and each city, a small load across the border of the law, that pays more than a plain gig and may be stopped by the
// police. How it ends is drawn when it is taken and frozen; the driver sees the chances first. An arrest blocks the journeys and the gigs of the driver, from the moment of the
// stop, until it is over or the bail is paid.

afterEach(fn () => Carbon::setTestNow());

/** The gigs of the repository with a change made to the document. @param  callable(array<string, mixed>): array<string, mixed>|null  $change */
function nightRules(?callable $change = null): void
{
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/gigs.json'), true, 512, JSON_THROW_ON_ERROR);

    app()->instance(GigCatalog::class, GigCatalog::fromArray($change === null ? $data : $change($data)));

    // The controllers of the routes keep the services they were built with from request to request, and these hold the catalog.
    foreach (app('router')->getRoutes() as $route) {
        $route->flushController();
    }
}

/**
 * The chances of the night run changed in the document: `$control` is the chance of a stop and `$escape` the part of the stops that the car drives away from, in parts per million.
 *
 * @param  array<string, mixed>  $night  more changes to the night run
 */
function nightEnds(int $control, int $escape, array $night = []): void
{
    nightRules(function (array $data) use ($control, $escape, $night): array {
        $data['night']['risk'] = [...$data['night']['risk'], 'control_ppm' => $control, 'escape_ppm' => $escape, 'modifiers' => []];
        $data['night'] = [...$data['night'], ...$night];

        return $data;
    });
}

/** A driver at the level where the night run opens, past the tutorial, with a garage in every city and the stock Rdzak (a hatchback) on its track at midnight of the game. @return array{0: User, 1: App\Models\RaceRun} */
function nightDriver(int $level = 9): array
{
    [$driver, $race] = driverWithRun(['seed' => str_repeat('0', 32)]);
    (new Ledger)->ensureWallet($driver->id);
    (new Ledger)->grantXp($driver->id, app(Rules::class)->playerXpToReach($level));

    foreach (array_keys(app(Rules::class)->regions()) as $region) {
        CityGarage::query()->firstOrCreate(['user_id' => $driver->id, 'region' => $region], ['capacity' => 3]);
    }

    TutorialProgress::query()->create(['user_id' => $driver->id, 'step_key' => 'away', 'state' => TutorialProgress::STATE_DONE]);

    return [$driver, $race];
}

function nightBoard(User $driver): TestResponse
{
    return test()->getJson('/api/v1/gigs', bearerFor($driver));
}

function nightTake(User $driver, string $offer): TestResponse
{
    return test()->postJson('/api/v1/gigs', ['offer' => $offer], bearerFor($driver));
}

/** The key of the night run on the board of the driver. */
function nightOffer(User $driver): string
{
    $opportunity = nightBoard($driver)->assertOk()->json('data.opportunity');

    expect($opportunity)->not->toBeNull();

    return $opportunity['key'];
}

/** @return list<string> the file and the place of every error the validator finds in the content */
function nightErrors(ContentSource $content): array
{
    return array_map(static fn (array $issue): string => "{$issue['file']} {$issue['pointer']}", PackValidator::fromConfig()->validateSource($content)->errors());
}

function nightCash(User $driver): int
{
    return (int) Wallet::query()->find($driver->id)?->cash_cents;
}

describe('the opportunity', function () {
    it('is one night run on the board at night, with the chances the driver would take it with and what it pays beside the plain pay of the same trip', function () {
        [$driver] = nightDriver();
        nightRules();

        $data = nightBoard($driver)->assertOk()->json('data');
        $opportunity = $data['opportunity'];

        // T0 of the tests is midnight of the game. The Rdzak is a hatchback: a quiet car, 36% of the patrols less 14 points.
        expect($data['phase'])->toBe('night')
            ->and($opportunity)->toMatchArray(['template' => 'night', 'kind' => 'night', 'kind_name' => 'Night run', 'title' => 'Cargo without an invoice', 'opportunity' => true, 'eligible' => true, 'capacity' => 'cargo_kg'])
            ->and($opportunity['night']['odds'])->toMatchArray(['patrol_ppm' => 360_000, 'class_ppm' => -140_000, 'control_ppm' => 220_000, 'clean_ppm' => 780_000, 'escaped_ppm' => 127_600, 'arrested_ppm' => 92_400, 'noise' => 'low'])
            ->and($opportunity['night'])->toMatchArray(['multiplier_ppm' => 2_500_000, 'escape_pay_ppm' => 500_000, 'arrest_ms' => 1_500_000, 'heated' => false])
            ->and($opportunity['reward_cents'])->toBeBetween((int) floor($opportunity['night']['plain_reward_cents'] * 2.5) - 3, (int) ceil($opportunity['night']['plain_reward_cents'] * 2.5) + 3)
            ->and($opportunity['night']['fine_cents'])->toBe((int) round($opportunity['reward_cents'] * 0.3))
            ->and($opportunity['night']['bail_cents'])->toBe((int) round($opportunity['reward_cents'] * 0.5))
            // The night that began at 22:00 of the game ends at 5:00, which is 2 250 000 ms after the midnight of T0.
            ->and($opportunity['until_ms'])->toBe(RUN_T0 + 5 * 450_000)
            ->and($data['limits'])->toMatchArray(['night_used' => 0, 'night_limit' => 2])
            ->and($data['arrest'])->toBeNull();
    });

    it('is the same for the whole night and in the next night another one, and is not on the board by day, or for a driver below its level', function () {
        [$driver] = nightDriver();
        nightRules();
        $first = nightOffer($driver);

        // Half an hour later it is the same night, and so the same offer; the next night of the game (three real hours on) has another.
        Carbon::setTestNow(Carbon::createFromTimestampMs(RUN_T0 + 1_800_000));
        expect(nightOffer($driver))->toBe($first);

        Carbon::setTestNow(Carbon::createFromTimestampMs(RUN_T0 + 10_800_000));
        expect(nightOffer($driver))->not->toBe($first);

        // At noon of the game there is none.
        Carbon::setTestNow(Carbon::createFromTimestampMs(RUN_T0 + 12 * 450_000));
        expect(nightBoard($driver)->json('data.opportunity'))->toBeNull();

        [$low] = nightDriver(level: 4);
        Carbon::setTestNow(Carbon::createFromTimestampMs(RUN_T0));
        expect(nightBoard($low)->json('data.opportunity'))->toBeNull();
    });

    it('is not offered when the game switches the night run off', function () {
        [$driver] = nightDriver();
        nightRules(function (array $data): array {
            $data['night']['enabled'] = false;

            return $data;
        });

        expect(nightBoard($driver)->json('data.opportunity'))->toBeNull();
    });
});

describe('the chances', function () {
    it('are changed by the body class of the car and by the heat of an escape, and add up to one', function () {
        nightRules();
        $night = app(GigCatalog::class)->night();

        $quiet = NightOdds::of($night, 'van', false);
        $plain = NightOdds::of($night, 'coupe', false);
        $loud = NightOdds::of($night, 'muscle', false);
        $heated = NightOdds::of($night, 'hatchback', true);

        expect([$quiet['control_ppm'], $plain['control_ppm'], $loud['control_ppm'], $heated['control_ppm']])->toBe([220_000, 360_000, 460_000, 320_000])
            ->and([$quiet['noise'], $plain['noise'], $loud['noise']])->toBe(['low', 'normal', 'high'])
            ->and($quiet['clean_ppm'] + $quiet['escaped_ppm'] + $quiet['arrested_ppm'])->toBe(1_000_000)
            ->and($loud['clean_ppm'] + $loud['escaped_ppm'] + $loud['arrested_ppm'])->toBe(1_000_000);
    });

    it('are the ones a draw is made with, the same every time for the same gig, and come out at the rate that is shown', function () {
        nightRules();
        $police = app(GigPolice::class);
        $odds = NightOdds::of(app(GigCatalog::class)->night(), 'hatchback', false);
        $ends = ['clean' => 0, 'escaped' => 0, 'arrested' => 0];

        foreach (range(1, 2000) as $run) {
            $ends[$police->outcome($odds, $run, RUN_T0, 'zlomowo.1.99.abcdef01')]++;
        }

        expect($police->outcome($odds, 7, RUN_T0, 'zlomowo.1.99.abcdef01'))->toBe($police->outcome($odds, 7, RUN_T0, 'zlomowo.1.99.abcdef01'))
            ->and($ends['clean'] / 2000)->toEqualWithDelta(0.78, 0.03)
            ->and($ends['escaped'] / 2000)->toEqualWithDelta(0.128, 0.025)
            ->and($ends['arrested'] / 2000)->toEqualWithDelta(0.092, 0.025);
    });
});

describe('taking it', function () {
    it('pays the whole pay when nobody stops the car, and the car is back on its track', function () {
        [$driver, $race] = nightDriver();
        nightEnds(0, 580_000);
        $offer = nightOffer($driver);
        $before = nightCash($driver);

        nightTake($driver, $offer)->assertCreated()->assertJsonPath('gig.police', null);
        $gig = Gig::query()->firstOrFail();

        expect($gig)->police_outcome->toBe('clean')
            ->and($gig->police_at_ms)->toBeNull()
            ->and($gig->paid_cents)->toBe($gig->reward_cents);

        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms));
        nightBoard($driver)->assertOk();

        expect(nightCash($driver))->toBe($before + $gig->reward_cents)
            ->and($gig->fresh())->status->toBe(Gig::STATUS_DONE)
            ->and($race->fresh()->stop_reason)->toBeNull();
    });

    it('pays half when the car gets away, and makes the patrols of that city look harder for it for a while', function () {
        [$driver] = nightDriver();
        nightEnds(1_000_000, 1_000_000);
        $offer = nightOffer($driver);
        $before = nightCash($driver);

        nightTake($driver, $offer)->assertCreated();
        $gig = Gig::query()->firstOrFail();

        expect($gig)->police_outcome->toBe('escaped')
            ->and($gig->police_at_ms)->toBe($gig->start_ms + intdiv($gig->finish_ms - $gig->start_ms, 2))
            ->and($gig->paid_cents)->toBe((int) round($gig->reward_cents * 0.5))
            ->and($gig->arrest_until_ms)->toBeNull();

        // Before the stop the driver knows nothing; after it, the board tells the end of the gig that is on the road.
        expect(nightBoard($driver)->json('data.active.police'))->toBeNull();

        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->police_at_ms + 1));
        expect(nightBoard($driver)->json('data.active.police.outcome'))->toBe('escaped')
            ->and(nightBoard($driver)->json('data.arrest'))->toBeNull();

        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms));
        nightBoard($driver)->assertOk();

        expect(nightCash($driver))->toBe($before + $gig->paid_cents);

        // Within the six hours after the escape the same city has the patrols looking harder (the next night is three real hours on).
        Carbon::setTestNow(Carbon::createFromTimestampMs(RUN_T0 + 10_800_000));
        nightRules();
        $next = nightBoard($driver)->json('data.opportunity.night');

        expect($next['heated'])->toBeTrue()->and($next['odds']['heat_ppm'])->toBe(100_000)->and($next['odds']['control_ppm'])->toBe(320_000);
    });

    it('pays nothing and fines the driver when they are arrested, from what they have, and gives no experience', function () {
        [$driver] = nightDriver();
        nightEnds(1_000_000, 0);
        $offer = nightOffer($driver);
        (new Ledger)->post($driver->id, 100_000, 'test', 'night-cash');
        $before = nightCash($driver);
        $xp = Wallet::query()->find($driver->id)->xp;

        nightTake($driver, $offer)->assertCreated();
        $gig = Gig::query()->firstOrFail();

        expect($gig)->police_outcome->toBe('arrested')
            ->and($gig->paid_cents)->toBe(0)
            ->and($gig->arrest_until_ms)->toBe($gig->police_at_ms + 1_500_000)
            ->and($gig->fine_cents)->toBe((int) round($gig->reward_cents * 0.3))
            ->and($gig->bail_cents)->toBe((int) round($gig->reward_cents * 0.5));

        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms));
        nightBoard($driver)->assertOk();

        expect(nightCash($driver))->toBe($before - $gig->fine_cents)
            ->and(LedgerEntry::query()->where('reason', 'gig')->count())->toBe(0)
            ->and(LedgerEntry::query()->where('reason', 'gig_fine')->count())->toBe(1)
            ->and(Wallet::query()->find($driver->id)->xp)->toBe($xp)
            ->and($gig->fresh())->fine_paid_cents->toBe($gig->fine_cents);
    });

    it('takes no more than the driver has for the fine', function () {
        [$driver] = nightDriver();
        nightEnds(1_000_000, 0);
        $offer = nightOffer($driver);
        Wallet::query()->where('user_id', $driver->id)->update(['cash_cents' => 500]);

        nightTake($driver, $offer)->assertCreated();
        $gig = Gig::query()->firstOrFail();
        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms));
        nightBoard($driver)->assertOk();

        expect(nightCash($driver))->toBe(0)->and($gig->fresh()->fine_paid_cents)->toBe(500);
    });

    it('is frozen when it is taken: what the rules say afterwards changes nothing in a night run on the road', function () {
        [$driver] = nightDriver();
        nightEnds(1_000_000, 1_000_000);
        $offer = nightOffer($driver);
        nightTake($driver, $offer)->assertCreated();
        $gig = Gig::query()->firstOrFail();
        $before = nightCash($driver);

        // The rules now say that nobody is ever stopped, and that a night run pays three times the plain pay.
        nightEnds(0, 0, ['pay_ppm' => 3_000_000]);
        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms));
        nightBoard($driver)->assertOk();

        expect(nightCash($driver))->toBe($before + $gig->paid_cents)->and($gig->fresh()->police_outcome)->toBe('escaped');
    });

    it('is refused once the limit of the night runs of the day is reached', function () {
        [$driver] = nightDriver();
        nightEnds(0, 0, ['daily_limit' => 1]);
        nightTake($driver, nightOffer($driver))->assertCreated();
        $gig = Gig::query()->firstOrFail();

        // The next night of the game is in the same day of the limit; the car is back by then.
        Carbon::setTestNow(Carbon::createFromTimestampMs(RUN_T0 + 10_800_000));
        nightEnds(0, 0, ['daily_limit' => 1]);
        $opportunity = nightBoard($driver)->assertOk()->json('data.opportunity');

        expect($opportunity['eligible'])->toBeFalse()->and($opportunity['blocked']['code'])->toBe('gig_night_limit');
        nightTake($driver, $opportunity['key'])->assertStatus(422)->assertJsonPath('code', 'gig_night_limit');
        expect($gig->fresh()->status)->toBe(Gig::STATUS_DONE);
    });

    it('is taken back in the first seconds without a trace of the police', function () {
        [$driver] = nightDriver();
        nightEnds(1_000_000, 0);
        nightTake($driver, nightOffer($driver))->assertCreated();
        $gig = Gig::query()->firstOrFail();

        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        test()->deleteJson("/api/v1/gigs/{$gig->id}", [], bearerFor($driver))->assertOk()->assertJsonPath('data.arrest', null);

        // The stop was to be in the middle of the trip, which the driver never made.
        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->police_at_ms + 1));
        expect(nightBoard($driver)->json('data.arrest'))->toBeNull()
            ->and(app(GigPolice::class)->arrestedUntilMs($driver->id, $gig->police_at_ms + 1))->toBeNull();
    });
});

describe('the arrest', function () {
    /** A driver who has taken a night run that ends in an arrest, with the clock at the moment of the stop. @return array{0: User, 1: Gig} */
    function nightArrested(): array
    {
        [$driver] = nightDriver();
        nightEnds(1_000_000, 0);
        nightTake($driver, nightOffer($driver))->assertCreated();
        $gig = Gig::query()->firstOrFail();
        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->police_at_ms + 1));

        return [$driver, $gig];
    }

    it('begins at the stop, not before it, and blocks the gigs and the journeys of the driver, and nothing else', function () {
        [$driver] = nightDriver();
        nightEnds(1_000_000, 0);
        nightTake($driver, nightOffer($driver))->assertCreated();
        $gig = Gig::query()->firstOrFail();

        // On the way, before the stop: no arrest yet.
        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->police_at_ms - 1));
        expect(nightBoard($driver)->json('data.arrest'))->toBeNull();

        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->police_at_ms + 1));
        $board = nightBoard($driver)->assertOk()->json('data');

        expect($board['arrest'])->toBe(['gig' => $gig->id, 'since_ms' => $gig->police_at_ms, 'until_ms' => $gig->arrest_until_ms, 'bail_cents' => $gig->bail_cents])
            ->and($board['active']['police'])->toMatchArray(['outcome' => 'arrested', 'bailed' => false, 'paid_cents' => 0]);

        nightTake($driver, 'zlomowo.1.0.abcdef01')->assertStatus(422)->assertJsonPath('code', 'arrested')->assertJsonPath('details.until_ms', $gig->arrest_until_ms);
        test()->postJson('/api/v1/travel', ['to' => 'kruszyn'], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'arrested');
        test()->postJson('/api/v1/travel', ['to' => 'kruszyn', 'boost' => true], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'arrested');

        // The garage and the workshop work on: the arrest is of the driver, not of their cars.
        test()->getJson('/api/v1/garage', bearerFor($driver))->assertOk();
    });

    it('is over when its time is over, also for a driver who was not there to see it', function () {
        [$driver, $gig] = nightArrested();

        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->arrest_until_ms));

        expect(nightBoard($driver)->json('data.arrest'))->toBeNull()
            ->and(app(GigPolice::class)->arrestedUntilMs($driver->id, $gig->arrest_until_ms))->toBeNull();
    });

    it('ends at once when the driver pays the bail, which is taken from their cash once', function () {
        [$driver, $gig] = nightArrested();
        (new Ledger)->post($driver->id, 100_000, 'test', 'night-bail-cash');
        $before = nightCash($driver);

        test()->postJson('/api/v1/gigs/bail', [], bearerFor($driver))->assertOk()->assertJsonPath('data.arrest', null);

        expect(nightCash($driver))->toBe($before - $gig->bail_cents)
            ->and(LedgerEntry::query()->where('reason', 'gig_bail')->count())->toBe(1)
            ->and($gig->fresh()->bailed_ms)->not->toBeNull();

        test()->postJson('/api/v1/gigs/bail', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'not_arrested');
        expect(nightCash($driver))->toBe($before - $gig->bail_cents);
    });

    it('is not ended by a bail that the driver cannot pay', function () {
        [$driver, $gig] = nightArrested();
        Wallet::query()->where('user_id', $driver->id)->update(['cash_cents' => 0]);

        test()->postJson('/api/v1/gigs/bail', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'insufficient_funds');

        expect(app(GigPolice::class)->arrestedUntilMs($driver->id, $gig->police_at_ms + 1))->toBe($gig->arrest_until_ms);
    });

    it('is asked of a driver who is not under arrest as an error', function () {
        [$driver] = nightDriver();

        test()->postJson('/api/v1/gigs/bail', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'not_arrested');
    });

    it('is told in the summary of the break, with the fine and what the gig paid, and the gig is not a gig done for the missions', function () {
        [$driver, $gig] = nightArrested();
        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms + 60_000));
        $driver->forceFill(['away_from' => Carbon::createFromTimestampMs(RUN_T0 - 3_600_000), 'away_to' => Carbon::createFromTimestampMs($gig->finish_ms + 60_000)])->save();

        $summary = test()->getJson('/api/v1/away', bearerFor($driver))->assertOk();

        expect($summary->json('data.gigs.0'))->toMatchArray(['kind' => 'night', 'kind_name' => 'Night run', 'reward_cents' => 0])
            ->and($summary->json('data.gigs.0.police'))->toMatchArray(['outcome' => 'arrested', 'arrest_until_ms' => $gig->arrest_until_ms])
            ->and($summary->json('data.gigs_cents'))->toBe(0);
    });
});

describe('the night run as content', function () {
    it('is valid in the pack that ships, with a kind of its own and a title in every language', function () {
        $night = GigCatalog::fromSource(repositoryContent())->night();

        expect(nightErrors(repositoryContent()))->toBe([])
            ->and($night)->toMatchArray(['kind' => 'night', 'pay_ppm' => 2_500_000, 'daily_limit' => 2])
            ->and(array_keys($night['title']))->toBe(['en', 'pl', 'fr', 'de', 'pt-BR']);
    });

    it('is refused by the validator for a kind that is not there, a load no car can carry, or a smallest load above the biggest', function () {
        $content = contentWith(['rules/gigs' => function (array $gigs): array {
            $gigs['night']['kind'] = 'balloon';
            $gigs['night']['amount'] = ['min' => 9000, 'max' => 9000];

            return $gigs;
        }]);

        expect(nightErrors($content))->toContain('rules/gigs.json /night/kind', 'rules/gigs.json /night/amount');

        $inverted = contentWith(['rules/gigs' => function (array $gigs): array {
            $gigs['night']['amount'] = ['min' => 60, 'max' => 10];

            return $gigs;
        }]);

        expect(nightErrors($inverted))->toContain('rules/gigs.json /night/amount');
    });

    it('is in the report of the pay with an average over its ends, and the report fails when it pays more than the bound', function () {
        $this->artisan('gigs:report', ['--strict' => true])->assertExitCode(0);

        nightRules(function (array $data): array {
            $data['night']['pay_ppm'] = 6_000_000;

            return $data;
        });

        $this->artisan('gigs:report', ['--strict' => true])->assertExitCode(1);
    });
});
