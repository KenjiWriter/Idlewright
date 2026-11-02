<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Domain\Market\Tuning;
use App\Models\LedgerEntry;
use App\Models\Part;
use App\Models\RaceRun;
use App\Models\User;
use App\Models\WorkshopJob;
use App\Services\Ledger;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

// Street tires of tier 2 cost $570. A try of tuning at a place of them costs 3% of that ($17.10) when it draws the bonus or tries again at the one the place holds,
// 10% ($57) when it picks another bonus, and takes half of what putting them on takes (235 200 ms): 117 600 ms.
const TUNE_RANDOM_CENTS = 1_710;
const TUNE_PICK_CENTS = 5_700;
const TUNE_MS = 117_600;

beforeEach(fn () => withoutTireFee());
afterEach(fn () => Carbon::setTestNow());

/**
 * A driver of level 3 with a car that drives (the seed is fixed, so it does not break down in the middle of a test), cash, and street tires of tier 2 in the
 * inventory.
 *
 * @return array{0: User, 1: RaceRun, 2: Part}
 */
function tuneDriver(array $part = [], int $level = 3, int $cash = 1_000_000): array
{
    [$driver, $run] = driverWithRun(['seed' => str_repeat('0', 32)]);
    (new Ledger)->post($driver->id, $cash, 'gift', "tune-test:{$driver->id}");
    (new Ledger)->grantXp($driver->id, app(Rules::class)->playerXpToReach($level));

    return [$driver, $run, Part::factory()->create(['user_id' => $driver->id, 'part_slug' => 'street-tires-t2', 'slot' => 'tires', ...$part])];
}

function tuneJob(User $driver, int $part, int $place = 1, ?string $key = null): TestResponse
{
    return test()->postJson('/api/v1/garage/jobs', ['kind' => 'tune', 'part' => $part, 'place' => $place, ...($key === null ? [] : ['key' => $key])], bearerFor($driver));
}

function tuneGarage(User $driver): TestResponse
{
    return test()->getJson('/api/v1/garage', bearerFor($driver));
}

/**
 * What the next try at a place gives, drawn the way the server draws it: the seed is the one of the driver, the part, the place and the number of the try.
 *
 * @param  array{key: string, ppm: int, tries: int, fails: int}|null  $held
 * @return array{key: string, ppm: int, tries: int, fails: int}
 */
function tuneExpected(User $driver, Part $part, int $place, ?array $held, ?string $key, int $level = 3): array
{
    $seed = Tuning::seed((string) config('app.key'), $driver->id, $part->id, $place, (int) ($held['tries'] ?? 0) + 1);

    return (new Tuning(app(Rules::class)->tuning()))->attempt($held, $key, $seed, $part->slot, $level) ?? throw new RuntimeException('The try gives nothing.');
}

describe('the price and the time of a try of tuning', function () {
    it('costs 3% of the price of the model for a draw and 10% for a pick, never less than $5, and takes half of what putting the part on takes', function () {
        $rules = app(Rules::class);

        expect($rules->tuningCostCents(57_000, false))->toBe(TUNE_RANDOM_CENTS)
            ->and($rules->tuningCostCents(57_000, true))->toBe(TUNE_PICK_CENTS)
            ->and($rules->tuningCostCents(10_000, false))->toBe(500)
            ->and($rules->tuningCostCents(10_000, true))->toBe(1_000)
            ->and($rules->tuningJobMs('tires', 2))->toBe(TUNE_MS)
            ->and($rules->tuningUnlockLevel())->toBe(3);
    });
});

describe('a try of tuning', function () {
    it('is paid when it is queued, waits for the mechanic, and leaves the part as it is until he is done', function () {
        [$driver, $run, $tires] = tuneDriver();

        $job = tuneJob($driver, $tires->id)->assertCreated()->assertJsonPath('data.workshop.jobs.0.status', 'working')->json('data.workshop.jobs.0');

        expect($job)->toMatchArray(['kind' => 'tune', 'cost_cents' => TUNE_RANDOM_CENTS, 'tune' => ['place' => 1, 'key' => null]])
            ->and($job['part']['id'])->toBe($tires->id)
            ->and($job['finish_ms'] - $job['start_ms'])->toBe(TUNE_MS)
            ->and(cashOf($run))->toBe(1_000_000 - TUNE_RANDOM_CENTS)
            // The tires are in the inventory, so the car does not stand for it.
            ->and(WorkshopJob::query()->first()->car_id)->toBeNull()
            ->and($tires->refresh()->tuning)->toBeNull();
    });

    it('puts the bonus it draws at the place when the mechanic is done: the one that the seed of the try gives', function () {
        [$driver, , $tires] = tuneDriver();
        tuneJob($driver, $tires->id, 2)->assertCreated();
        clockAt(TUNE_MS + 1_000);

        $expected = tuneExpected($driver, $tires, 2, null, null);
        $places = tuneGarage($driver)->assertOk()->assertJsonPath('data.workshop.jobs', [])->json('data.inventory.0.tuning');

        expect($places[0])->toBeNull()
            ->and($places[1])->toMatchArray(['place' => 2, 'key' => $expected['key'], 'value_ppm' => $expected['ppm'], 'tries' => 1])
            ->and($tires->refresh()->tuning)->toBe([null, $expected, null]);
    });

    it('puts the bonus that is picked for ten percent of the price, and gives it no less than a sigma under its mean', function () {
        [$driver, , $tires] = tuneDriver();
        $job = tuneJob($driver, $tires->id, 1, 'dry-grip')->assertCreated()->json('data.workshop.jobs.0');
        clockAt(TUNE_MS + 1_000);
        tuneGarage($driver)->assertOk();

        expect($job['cost_cents'])->toBe(TUNE_PICK_CENTS)
            ->and($job['tune'])->toBe(['place' => 1, 'key' => 'dry-grip'])
            ->and($tires->refresh()->tuning[0])->toBe(tuneExpected($driver, $tires, 1, null, 'dry-grip'))
            // 5% less the 2.3% of a sigma.
            ->and($tires->tuning[0]['ppm'])->toBeGreaterThanOrEqual(27_000);
    });

    it('tries again at the bonus a place holds for the smaller price, and never ends with less than it had', function () {
        $held = ['key' => 'dry-grip', 'ppm' => 95_000, 'tries' => 4, 'fails' => 3];
        [$driver, , $tires] = tuneDriver(['tuning' => [$held, null, null]]);

        $job = tuneJob($driver, $tires->id)->assertCreated()->json('data.workshop.jobs.0');
        clockAt(TUNE_MS + 1_000);
        tuneGarage($driver)->assertOk();

        expect($job['cost_cents'])->toBe(TUNE_RANDOM_CENTS)
            // The try at the place that has a bonus is a try at that bonus, not a draw of another.
            ->and($tires->refresh()->tuning[0])->toBe(tuneExpected($driver, $tires, 1, $held, 'dry-grip'))
            ->and($tires->tuning[0]['ppm'])->toBeGreaterThanOrEqual(95_000)
            ->and($tires->tuning[0]['tries'])->toBe(5);
    });

    it('costs the smaller price too when the bonus that is picked is the one the place holds, and the larger one when it is another', function () {
        $held = ['key' => 'dry-grip', 'ppm' => 60_000, 'tries' => 2, 'fails' => 0];
        [$driver, , $tires] = tuneDriver(['tuning' => [$held, null, null]], cash: 1_000_000);

        $same = tuneJob($driver, $tires->id, 1, 'dry-grip')->assertCreated()->json('data.workshop.jobs.0');
        $other = tuneJob($driver, $tires->id, 2, 'gravel-grip')->assertCreated()->json('data.workshop.jobs.1');

        expect($same['cost_cents'])->toBe(TUNE_RANDOM_CENTS)->and($other['cost_cents'])->toBe(TUNE_PICK_CENTS);
    });

    it('replaces the bonus of a place with another that is picked, which starts no lower than a sigma under its mean', function () {
        $held = ['key' => 'dry-grip', 'ppm' => 95_000, 'tries' => 4, 'fails' => 3];
        [$driver, , $tires] = tuneDriver(['tuning' => [$held, null, null]]);

        tuneJob($driver, $tires->id, 1, 'gravel-grip')->assertCreated();
        clockAt(TUNE_MS + 1_000);
        tuneGarage($driver)->assertOk();

        expect($tires->refresh()->tuning[0])->toBe(tuneExpected($driver, $tires, 1, $held, 'gravel-grip'))
            ->and($tires->tuning[0]['key'])->toBe('gravel-grip')
            ->and($tires->tuning[0]['ppm'])->toBeGreaterThanOrEqual(27_000)
            // The number of the try goes on, and the count of the tries that did not help starts again.
            ->and($tires->tuning[0]['tries'])->toBe(5)
            ->and($tires->tuning[0]['fails'])->toBe(0);
    });

    it('may wait at different places of one part together, but not twice at the same place', function () {
        [$driver, , $tires] = tuneDriver();

        tuneJob($driver, $tires->id, 1)->assertCreated();
        tuneJob($driver, $tires->id, 2)->assertCreated();
        tuneJob($driver, $tires->id, 1)->assertStatus(422)->assertJsonPath('code', 'tuning_place_busy');
        // Another kind of job on a part that waits for tuning is refused, and so is tuning a part that waits for another job.
        test()->postJson('/api/v1/garage/jobs', ['kind' => 'mount', 'part' => $tires->id], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'part_busy');

        [$other, , $stock] = tuneDriver();
        test()->postJson('/api/v1/garage/jobs', ['kind' => 'mount', 'part' => $stock->id], bearerFor($other))->assertCreated();
        tuneJob($other, $stock->id)->assertStatus(422)->assertJsonPath('code', 'part_busy');
        expect(WorkshopJob::query()->where('kind', 'tune')->count())->toBe(2);
    });

    it('stands the car in the workshop only when the part is on it', function () {
        [$driver, $run] = tuneDriver();
        $brakes = Part::factory()->state(['part_slug' => 'disc-brakes-t2', 'slot' => 'brakes'])->mountedOn($run->car)->create();

        tuneJob($driver, $brakes->id)->assertCreated()
            ->assertJsonPath('data.workshop.jobs.0.status', 'working')
            ->assertJsonPath('data.car.run.stop_reason', 'workshop');
        expect(WorkshopJob::query()->first()->car_id)->toBe($run->car->id);

        clockAt(app(Rules::class)->tuningJobMs('brakes', 2) + 1_000);
        tuneGarage($driver)->assertOk()->assertJsonPath('data.car.run.stop_reason', null);

        expect($brakes->refresh()->tuning[0])->not->toBeNull();
    });
});

describe('a try of tuning that is refused', function () {
    it('asks for the level of the driver, and costs nothing', function () {
        [$driver, $run, $tires] = tuneDriver(level: 2);

        tuneJob($driver, $tires->id)->assertStatus(422)->assertJsonPath('code', 'tuning_level_required');

        expect(cashOf($run))->toBe(1_000_000)->and(WorkshopJob::query()->count())->toBe(0);
    });

    it('asks for a place the part has, a bonus that fits its slot and is on offer at the level, and one that the part does not hold at another place', function () {
        $held = ['key' => 'dry-grip', 'ppm' => 60_000, 'tries' => 1, 'fails' => 0];
        [$driver, , $tires] = tuneDriver(['tuning' => [$held, null, null]]);

        tuneJob($driver, $tires->id, 0)->assertStatus(422);
        tuneJob($driver, $tires->id, 4)->assertStatus(422);
        // A bonus for the gearbox, one that opens at level 8, one that the pack does not know, and the one that is at the first place.
        tuneJob($driver, $tires->id, 2, 'sprint-launch')->assertStatus(422)->assertJsonPath('code', 'tuning_bonus_unavailable');
        tuneJob($driver, $tires->id, 2, 'snow-grip')->assertStatus(422)->assertJsonPath('code', 'tuning_bonus_unavailable');
        tuneJob($driver, $tires->id, 2, 'vanished')->assertStatus(422)->assertJsonPath('code', 'tuning_bonus_unavailable');
        tuneJob($driver, $tires->id, 2, 'dry-grip')->assertStatus(422)->assertJsonPath('code', 'tuning_bonus_unavailable');

        // Nor the same bonus at two places by two tries that wait.
        tuneJob($driver, $tires->id, 2, 'gravel-grip')->assertCreated();
        tuneJob($driver, $tires->id, 3, 'gravel-grip')->assertStatus(422)->assertJsonPath('code', 'tuning_bonus_unavailable');
        expect(WorkshopJob::query()->count())->toBe(1);
    });

    it('needs the cash, and a part of the driver', function () {
        [$poor, $run, $tires] = tuneDriver(cash: 1_000);
        [$driver, , $stock] = tuneDriver();

        tuneJob($poor, $tires->id)->assertStatus(422)->assertJsonPath('code', 'insufficient_funds');
        tuneJob($poor, $stock->id)->assertStatus(422)->assertJsonPath('code', 'part_not_found');

        expect(cashOf($run))->toBe(1_000)->and(WorkshopJob::query()->count())->toBe(0);
    });
});

describe('cancelling a try of tuning', function () {
    it('gives the price back whole when the mechanic has not started, and loses it when he is at it', function () {
        [$driver, $run, $tires] = tuneDriver();

        // The first try is the one the mechanic is at, the second waits for him.
        $at = tuneJob($driver, $tires->id, 1)->assertCreated()->json('data.workshop.jobs.0.id');
        $waits = tuneJob($driver, $tires->id, 2)->assertCreated()->assertJsonPath('data.workshop.jobs.1.status', 'queued')->json('data.workshop.jobs.1.id');
        expect(cashOf($run))->toBe(1_000_000 - 2 * TUNE_RANDOM_CENTS);

        test()->deleteJson("/api/v1/garage/jobs/{$waits}", [], bearerFor($driver))->assertOk();
        expect(cashOf($run))->toBe(1_000_000 - TUNE_RANDOM_CENTS)
            ->and(LedgerEntry::query()->where('reason', 'tune_refund')->count())->toBe(1);

        test()->deleteJson("/api/v1/garage/jobs/{$at}", [], bearerFor($driver))->assertOk();
        expect(cashOf($run))->toBe(1_000_000 - TUNE_RANDOM_CENTS)->and($tires->refresh()->tuning)->toBeNull();
    });

    it('gives the price back when the bonus can no longer be put there once the mechanic is done', function () {
        [$driver, $run, $tires] = tuneDriver();

        tuneJob($driver, $tires->id, 1, 'dry-grip')->assertCreated();
        // In the meantime the part got the same bonus at another place (the pack was changed, or a try drew it): the try has nothing to do.
        $other = ['key' => 'dry-grip', 'ppm' => 50_000, 'tries' => 1, 'fails' => 0];
        $tires->forceFill(['tuning' => [null, $other, null]])->save();
        clockAt(TUNE_MS + 1_000);
        tuneGarage($driver)->assertOk();

        expect(cashOf($run))->toBe(1_000_000)
            ->and($tires->refresh()->tuning)->toBe([null, $other, null])
            ->and(LedgerEntry::query()->where('reason', 'tune_refund')->count())->toBe(1);
    });
});

describe('a try of tuning in the garage', function () {
    it('can be finished at once for premium coins, like any job of the workshop', function () {
        [$driver, , $tires] = tuneDriver();
        (new Ledger)->postPremium($driver->id, 3, 'gift', "tune-coins:{$driver->id}");
        $job = tuneJob($driver, $tires->id)->assertCreated()->json('data.workshop.jobs.0');

        test()->postJson("/api/v1/garage/jobs/{$job['id']}/finish", ['request_id' => 'r-1'], bearerFor($driver))->assertOk()->assertJsonPath('spent_coins', 1);

        expect($tires->refresh()->tuning[0])->toBe(tuneExpected($driver, $tires, 1, null, null));
    });

    it('is offered with its prices, its time and the bonuses of the slot at the level of the driver, for the parts in the inventory and the ones on the car', function () {
        [$driver, $run, $tires] = tuneDriver();
        Part::factory()->state(['part_slug' => 'disc-brakes-t2', 'slot' => 'brakes'])->mountedOn($run->car)->create();

        $garage = tuneGarage($driver)->assertOk();
        $offer = $garage->json('data.inventory.0.tune_next');
        $keys = array_column($offer['bonuses'], 'key');
        $onCar = collect($garage->json('data.car.slots'))->firstWhere('slot', 'brakes')['part']['tune_next'];

        expect($offer)->toMatchArray(['locked' => null, 'cost_random_cents' => TUNE_RANDOM_CENTS, 'cost_pick_cents' => TUNE_PICK_CENTS, 'duration_ms' => TUNE_MS])
            ->and($keys)->toContain('dry-grip', 'gravel-grip')
            // The bonus for the gearbox is not for tires, and the one for snow opens at level 8.
            ->and($keys)->not->toContain('sprint-launch', 'snow-grip')
            ->and($offer['bonuses'][0])->toHaveKeys(['key', 'name', 'description', 'effect', 'stat', 'context', 'mean_percent', 'max_percent'])
            ->and($onCar['locked'])->toBeNull()
            ->and(array_column($onCar['bonuses'], 'key'))->toContain('sprint-brake');
    });

    it('says why a part cannot be tuned yet: the driver is not of the level, so there is no price and no bonus to pick from', function () {
        [$driver] = tuneDriver(level: 1);

        $offer = tuneGarage($driver)->assertOk()->json('data.inventory.0.tune_next');

        expect($offer['locked'])->toBe(['reason' => 'level', 'required' => 3, 'current' => 1])
            ->and($offer['cost_random_cents'])->toBeNull()
            ->and($offer['bonuses'])->toBe([]);
    });

    it('is told in the language of the driver', function () {
        [$driver] = tuneDriver();

        $names = collect(test()->getJson('/api/v1/garage', [...bearerFor($driver), 'X-Locale' => 'pl'])->assertOk()->json('data.inventory.0.tune_next.bonuses'))->pluck('name', 'key');

        expect($names['dry-grip'])->toBe('Przyczepność na suchym');
    });
});
