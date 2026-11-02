<?php

declare(strict_types=1);

use App\Content\ContentSource;
use App\Content\GigCatalog;
use App\Content\PackValidator;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Models\Car;
use App\Models\CityGarage;
use App\Models\Gig;
use App\Models\LedgerEntry;
use App\Models\Part;
use App\Models\RaceRun;
use App\Models\TutorialProgress;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

// The gigs: a round trip of a car with people or a load. The board is made again from a seed, a gig that is taken is paid once when the car is back, and the car
// drives its track again from that moment. The tests that need a particular offer run on a board with one template, so that they do not depend on the draw.

afterEach(fn () => Carbon::setTestNow());

/** The gigs of the repository with a change made to the document, for the tests of a board that is not the one that ships. @param  callable(array<string, mixed>): array<string, mixed>  $change */
function gigRules(callable $change): void
{
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/gigs.json'), true, 512, JSON_THROW_ON_ERROR);

    app()->instance(GigCatalog::class, GigCatalog::fromArray($change($data)));
}

/** A board with one kind of offer in every phase: a ride of 10 km for one person at the plain pay. @return array<string, mixed> */
function oneTemplate(array $template = [], array $more = []): array
{
    return [
        'kind' => 'taxi', 'title' => ['en' => 'Ride', 'pl' => 'Kurs'], 'capacity' => 'seats', 'amount' => ['min' => 1, 'max' => 1], 'mode' => 'local',
        'local_km' => ['min' => 10, 'max' => 10], 'pay_ppm' => 1_000_000, 'phases' => ['dawn' => 1, 'day' => 1, 'dusk' => 1, 'night' => 1], 'window_waves' => 4, 'min_player_level' => 1,
        ...$template,
        ...$more,
    ];
}

/** The board of the repository with the templates replaced by one. */
function boardOf(array $template = [], array $change = []): void
{
    gigRules(function (array $data) use ($template, $change): array {
        $data['templates'] = ['ride' => oneTemplate($template)];

        return [...$data, ...$change];
    });
}

/** A driver of level 3 past the tutorial, with a garage in every city and the stock Rdzak on its track from T0. @return array{0: User, 1: RaceRun} */
function gigDriver(bool $tutorialDone = true): array
{
    [$driver, $race] = driverWithRun(['seed' => str_repeat('0', 32)]);
    (new Ledger)->ensureWallet($driver->id);
    (new Ledger)->grantXp($driver->id, app(Rules::class)->playerXpToReach(3));

    foreach (array_keys(app(Rules::class)->regions()) as $region) {
        CityGarage::query()->firstOrCreate(['user_id' => $driver->id, 'region' => $region], ['capacity' => 3]);
    }

    if ($tutorialDone) {
        TutorialProgress::query()->create(['user_id' => $driver->id, 'step_key' => 'away', 'state' => TutorialProgress::STATE_DONE]);
    }

    return [$driver, $race];
}

function gigBoard(User $driver): TestResponse
{
    return test()->getJson('/api/v1/gigs', bearerFor($driver));
}

function takeGig(User $driver, string $offer): TestResponse
{
    return test()->postJson('/api/v1/gigs', ['offer' => $offer], bearerFor($driver));
}

function driverCash(User $driver): int
{
    return (int) Wallet::query()->find($driver->id)?->cash_cents;
}

/** The key of the first offer of the board that nothing stands in the way of. */
function firstOffer(User $driver): string
{
    $offers = collect(gigBoard($driver)->json('data.offers'))->where('eligible', true);

    expect($offers)->not->toBeEmpty();

    return $offers->first()['key'];
}

/** @return list<string> the file and the place of every error the validator finds in the content */
function gigErrors(ContentSource $content): array
{
    return array_map(static fn (array $issue): string => "{$issue['file']} {$issue['pointer']}", PackValidator::fromConfig()->validateSource($content)->errors());
}

describe('the board', function () {
    it('is the same every time it is looked at, and a driver has a board of their own', function () {
        [$one] = gigDriver();
        [$two] = gigDriver();

        $first = collect(gigBoard($one)->assertOk()->json('data.offers'))->pluck('key');
        $again = collect(gigBoard($one)->json('data.offers'))->pluck('key');
        $other = collect(gigBoard($two)->json('data.offers'))->pluck('key');

        expect($first)->not->toBeEmpty()
            ->and($again->all())->toBe($first->all())
            ->and($other->intersect($first)->all())->toBe([]);
    });

    it('says the phase of the day and when the wave ends, and what each offer pays, takes and burns', function () {
        [$driver] = gigDriver();

        $data = gigBoard($driver)->assertOk()->json('data');
        $offer = $data['offers'][0];

        // T0 of the tests is midnight of the game: the night.
        expect($data['phase'])->toBe('night')
            ->and($data['wave']['ends_ms'])->toBe(RUN_T0 + 1_350_000)
            ->and($data['car'])->toMatchArray(['seats' => 4, 'cargo_kg' => 120, 'class' => 'hatchback'])
            ->and($offer['reward_cents'])->toBeGreaterThan(0)
            ->and($offer['duration_ms'])->toBeGreaterThan(0)
            ->and($offer['fuel_ml'])->toBeGreaterThan(0)
            ->and($data['limits'])->toMatchArray(['daily_used' => 0]);
    });

    it('is closed to a driver who has not got far enough in the tutorial', function () {
        [$driver] = gigDriver(tutorialDone: false);

        gigBoard($driver)->assertOk()->assertJsonPath('data.locked', 'tutorial')->assertJsonPath('data.offers', []);
        takeGig($driver, 'zlomowo.1.0.abcdef01')->assertStatus(422)->assertJsonPath('code', 'gigs_unavailable');
    });

    it('shows why a car cannot take an offer: too few seats, too little room, a class, a deadline it would miss', function (array $template, string $code, ?string $what, ?array $details) {
        [$driver] = gigDriver();
        boardOf($template);

        $blocked = gigBoard($driver)->json('data.offers.0.blocked');

        expect($blocked['code'])->toBe($code)
            ->and($blocked['details']['what'] ?? null)->toBe($what);

        if ($details !== null) {
            expect($blocked['details'])->toMatchArray($details);
        }

        takeGig($driver, gigBoard($driver)->json('data.offers.0.key'))->assertStatus(422)->assertJsonPath('code', $code);
    })->with([
        'too little room' => [['capacity' => 'cargo_kg', 'amount' => ['min' => 500, 'max' => 500]], 'gig_requirements', 'cargo_kg', ['need' => 500, 'have' => 120]],
        'too few seats' => [['amount' => ['min' => 5, 'max' => 5]], 'gig_requirements', 'seats', ['need' => 5, 'have' => 4]],
        'not the class' => [['class_in' => ['van']], 'gig_requirements', 'class', null],
        // The car has to finish within half the reference time, and it drives no faster than the limit of the roads.
        'a deadline it would miss' => [['deadline_ppm' => 500_000], 'gig_deadline', null, null],
    ]);

    it('pays by the rung of the ladder of the car, a part of it by the level of the driver, for the reference time of the trip', function () {
        [$driver] = gigDriver();
        boardOf();

        $offer = gigBoard($driver)->json('data.offers.0');

        // The Rdzak GT-4 is sold from level 3: 690 dollars an hour; at level 3 a gig pays 62% of it; 10 km take 514 285 ms at the reference speed of 70 km/h.
        expect($offer['reward_cents'])->toBe(intdiv((int) (69_000 * 0.62) * 514_285 + 1_800_000, 3_600_000))
            ->and($offer['duration_ms'])->toBe(450_000)
            ->and($offer['bonus_cents'])->toBe(0);
    });

    it('adds the bonus of the VIP account to the pay', function () {
        [$driver] = gigDriver();
        boardOf();
        $base = gigBoard($driver)->json('data.offers.0.reward_cents');

        Wallet::query()->where('user_id', $driver->id)->update(['vip_until' => Carbon::now()->addDay()]);
        $offer = gigBoard($driver)->json('data.offers.0');

        expect($offer['bonus_cents'])->toBeGreaterThan(0)->and($offer['reward_cents'])->toBe($base + $offer['bonus_cents']);
    });
});

describe('taking a gig', function () {
    it('sends the car out: it stands, its fuel for the trip is out of the tank, and every number is frozen', function () {
        [$driver, $race] = gigDriver();
        boardOf();
        $offer = firstOffer($driver);

        $response = takeGig($driver, $offer)->assertCreated();
        $gig = Gig::query()->firstOrFail();
        $run = $race->fresh();

        expect($response->json('repeated'))->toBeFalse()
            ->and($response->json('gig.status'))->toBe('active')
            ->and($gig->offer_key)->toBe($offer)
            ->and($gig->finish_ms)->toBe(RUN_T0 + 450_000)
            ->and($run->status)->toBe(RaceRun::STATUS_STOPPED)
            ->and($run->stop_reason)->toBe(RaceRun::STOP_GIG)
            ->and($run->fuel_ml)->toBe(45_000 - $gig->fuel_ml);
    });

    it('does nothing the second time it is asked for the same offer', function () {
        [$driver, $race] = gigDriver();
        boardOf();
        $offer = firstOffer($driver);

        takeGig($driver, $offer)->assertCreated();
        $fuel = $race->fresh()->fuel_ml;
        takeGig($driver, $offer)->assertOk()->assertJsonPath('repeated', true);

        expect(Gig::query()->count())->toBe(1)->and($race->fresh()->fuel_ml)->toBe($fuel);
    });

    it('refuses a key that is not on the board of the driver', function () {
        [$driver] = gigDriver();
        boardOf();

        takeGig($driver, 'zlomowo.1327920.0.00000000')->assertStatus(422)->assertJsonPath('code', 'gig_gone');
    });

    it('refuses a car that is out already, and keeps the workshop and the roads from a car that is out', function () {
        [$driver] = gigDriver();
        boardOf();
        $offers = collect(gigBoard($driver)->json('data.offers'))->pluck('key');

        takeGig($driver, $offers[0])->assertCreated();
        takeGig($driver, $offers[1])->assertStatus(422)->assertJsonPath('code', 'gig_busy');
        test()->postJson('/api/v1/travel', ['to' => 'kruszyn'], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'car_cannot_travel');
    });

    it('refuses a gig when the tank does not hold the fuel of the trip', function () {
        [$driver, $race] = gigDriver();
        boardOf();
        $race->forceFill(['fuel_ml' => 100])->save();

        expect(gigBoard($driver)->json('data.offers.0.blocked.code'))->toBe('gig_no_fuel');
        takeGig($driver, gigBoard($driver)->json('data.offers.0.key'))->assertStatus(422)->assertJsonPath('code', 'gig_no_fuel');
    });

    it('can be taken back in the first seconds: the fuel returns, nothing is paid, and the offer is spent', function () {
        [$driver, $race] = gigDriver();
        boardOf();
        $offer = firstOffer($driver);
        takeGig($driver, $offer)->assertCreated();
        $id = Gig::query()->firstOrFail()->id;

        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        test()->deleteJson("/api/v1/gigs/{$id}", [], bearerFor($driver))->assertOk();

        $run = $race->fresh();
        expect(Gig::query()->find($id)->status)->toBe(Gig::STATUS_CANCELLED)
            ->and($run->fuel_ml)->toBe(45_000)
            ->and($run->stop_reason)->toBeNull()
            ->and($run->status)->toBe(RaceRun::STATUS_RUNNING)
            ->and(LedgerEntry::query()->where('reason', 'gig')->count())->toBe(0);

        takeGig($driver, $offer)->assertStatus(422)->assertJsonPath('code', 'gig_gone');
    });

    it('cannot be taken back after the first seconds', function () {
        [$driver] = gigDriver();
        boardOf();
        takeGig($driver, firstOffer($driver))->assertCreated();
        $id = Gig::query()->firstOrFail()->id;

        Carbon::setTestNow(Carbon::now()->addSeconds(61));

        test()->deleteJson("/api/v1/gigs/{$id}", [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'gig_cancel_window');
    });

    it('stops at the limit of a day, and says when the day turns over', function () {
        [$driver] = gigDriver();
        boardOf(change: ['bands' => [['from_level' => 1, 'ratio_ppm' => 620_000, 'max_ref_minutes' => 120, 'daily_limit' => 1]]]);
        takeGig($driver, firstOffer($driver))->assertCreated();

        Carbon::setTestNow(Carbon::createFromTimestampMs(RUN_T0 + 600_000));
        $board = gigBoard($driver)->assertOk();

        expect($board->json('data.limits'))->toMatchArray(['daily_used' => 1, 'daily_limit' => 1])
            ->and($board->json('data.offers.0.blocked.code'))->toBe('gig_daily_limit');
        takeGig($driver, $board->json('data.offers.0.key'))->assertStatus(422)->assertJsonPath('code', 'gig_daily_limit');
    });
});

describe('a gig on the road', function () {
    it('pays once when the car is back, gives the experience, and puts the car on its track from that moment', function () {
        [$driver, $race] = gigDriver();
        boardOf();
        $xp = (new Ledger)->xp($driver->id);
        $cash = driverCash($driver);
        takeGig($driver, firstOffer($driver))->assertCreated();
        $gig = Gig::query()->firstOrFail();

        // Before it is back it has paid nothing and the car stands.
        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms - 1));
        gigBoard($driver)->assertOk()->assertJsonPath('data.active.status', 'active');
        expect(driverCash($driver))->toBe($cash)->and($race->fresh()->stop_reason)->toBe(RaceRun::STOP_GIG);

        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms));
        gigBoard($driver)->assertOk();
        gigBoard($driver)->assertOk();
        test()->getJson('/api/v1/cars', bearerFor($driver))->assertOk();

        $run = $race->fresh();
        expect(driverCash($driver))->toBe($cash + $gig->reward_cents)
            ->and((new Ledger)->xp($driver->id))->toBe($xp + $gig->xp)
            ->and($gig->fresh()->status)->toBe(Gig::STATUS_DONE)
            ->and(LedgerEntry::query()->where('reason', 'gig')->where('idem_key', "gig:{$gig->id}")->count())->toBe(1)
            ->and($run->status)->toBe(RaceRun::STATUS_RUNNING)
            ->and($run->stop_reason)->toBeNull()
            ->and($run->cursor_ms)->toBe($gig->finish_ms)
            ->and($run->laps_done)->toBe(0);
    });

    it('is finished by whoever asks first after its time, also when the driver was away for hours, and the laps count from the moment it was back', function () {
        [$driver, $race] = gigDriver();
        boardOf();
        takeGig($driver, firstOffer($driver))->assertCreated();
        $gig = Gig::query()->firstOrFail();

        Carbon::setTestNow(Carbon::createFromTimestampMs(RUN_T0 + 3 * 3_600_000));
        getRun($driver)->assertOk();

        $run = $race->fresh();

        // The tank (minus the fuel of the trip) lasts 29 laps of 75 s; they were driven after the gig, none during it.
        expect(Gig::query()->count())->toBe(1)
            ->and(LedgerEntry::query()->where('reason', 'gig')->count())->toBe(1)
            ->and($run->laps_done)->toBe(29)
            ->and($run->fuel_ml)->toBeLessThan(1_500)
            ->and($run->stop_reason)->toBe('fuel_empty');
    });

    it('burns the fuel of the trip when the car leaves, and puts the kilometres of the trip on its odometer and on its parts when it is back, like a journey', function () {
        [$driver, $race] = gigDriver();
        boardOf();
        $race->forceFill(['config' => [...$race->config, 'wear' => ['length_m' => 2_600, 'ppb_per_lap' => ['tires' => 1_000], 'ppb_per_km' => ['tires' => 25_000]]]])->save();
        $part = Part::factory()->create(['user_id' => $driver->id, 'car_id' => $race->car_id, 'slot' => 'tires', 'mounted_slot' => 'tires', 'wear_ppb' => 0]);
        $mileage = Car::query()->find($race->car_id)->mileage_m;

        takeGig($driver, firstOffer($driver))->assertCreated();
        $gig = Gig::query()->firstOrFail();

        // The fuel is out of the tank at once, the kilometres are not on the car yet.
        expect($race->fresh()->fuel_ml)->toBe(45_000 - $gig->fuel_ml)
            ->and($gig->fuel_ml)->toBe((int) ceil($gig->distance_km * app(Rules::class)->travelFuelMlPerKm() * app(RunInputsFactory::class)->stats('rdzak-gt-4')->fuelUseMultiplier))
            ->and(Car::query()->find($race->car_id)->mileage_m)->toBe($mileage);

        Carbon::setTestNow(Carbon::createFromTimestampMs($gig->finish_ms));
        gigBoard($driver)->assertOk();

        expect(Car::query()->find($race->car_id)->mileage_m)->toBe($mileage + 10_000)
            ->and($part->fresh()->wear_ppb)->toBe(10 * 25_000);
    });

    it('keeps the workshop from a car that is out', function () {
        [$driver] = gigDriver();
        boardOf();
        takeGig($driver, firstOffer($driver))->assertCreated();

        test()->postJson('/api/v1/garage/jobs', ['kind' => 'mount', 'part' => 1], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'car_on_gig');
    });
});

describe('the gigs as content', function () {
    it('are valid in the pack that ships', function () {
        $catalog = GigCatalog::fromSource(repositoryContent());

        expect(gigErrors(repositoryContent()))->toBe([])
            ->and($catalog->enabled())->toBeTrue()
            ->and(array_keys($catalog->templates()))->toContain('taxi-city', 'delivery-trip', 'bulk-trip');
    });

    it('are refused by the validator when a template asks for more than any car of the pack has, or names a kind that is not there, or the levels do not start at one', function () {
        $content = contentWith(['rules/gigs' => function (array $gigs): array {
            $gigs['templates']['too-big'] = [...$gigs['templates']['freight-trip'], 'amount' => ['min' => 9000, 'max' => 9000]];
            $gigs['templates']['no-kind'] = [...$gigs['templates']['freight-trip'], 'kind' => 'balloon'];
            $gigs['bands'][0]['from_level'] = 2;

            return $gigs;
        }]);

        expect(gigErrors($content))->toContain('rules/gigs.json /templates/too-big/amount', 'rules/gigs.json /templates/no-kind/kind', 'rules/gigs.json /bands');
    });
});

describe('the report', function () {
    it('measures the pay of an hour of a gig against the rung of the ladder and against the laps of the same car, for every car that can take one', function () {
        $this->artisan('gigs:report', ['--json' => true])->assertExitCode(0);

        $rows = collect(Artisan::call('gigs:report', ['--json' => true]) === 0 ? json_decode(Artisan::output(), true)['rows'] : []);
        $taxi = $rows->firstWhere(fn (array $row): bool => $row['car'] === 'rdzak-gt-4' && $row['template'] === 'taxi-city');

        // The Rdzak GT-4 is a car of level 3 (690 dollars an hour); it drives faster than the roads allow, so it pays the plain share of the rung.
        expect($rows->count())->toBeGreaterThan(50)
            ->and($taxi['anchor'])->toEqual(690)
            ->and($taxi['r1'])->toBeGreaterThan(0.55)->toBeLessThan(0.75)
            ->and($taxi['r2'])->toBeLessThan(1.0)
            // A van is asked for by the deliveries, and a small hatchback is not.
            ->and($rows->contains(fn (array $row): bool => $row['car'] === 'rdzak-gt-4' && $row['kind'] === 'delivery'))->toBeFalse()
            ->and($rows->contains(fn (array $row): bool => $row['car'] === 'furgon-z-1' && $row['template'] === 'delivery-city'))->toBeTrue();
    });

    it('finds no gig out of the bounds in the pack that ships, even with --strict', function () {
        $this->artisan('gigs:report', ['--strict' => true])->assertExitCode(0);
    });

    it('fails with --strict when a gig pays more than the rung of the ladder allows', function () {
        gigRules(function (array $gigs): array {
            foreach ($gigs['bands'] as $index => $band) {
                $gigs['bands'][$index]['ratio_ppm'] = 2_000_000;
            }

            return $gigs;
        });

        $this->artisan('gigs:report', ['--strict' => true])->assertExitCode(1);
        $this->artisan('gigs:report')->assertExitCode(0);
    });
});
