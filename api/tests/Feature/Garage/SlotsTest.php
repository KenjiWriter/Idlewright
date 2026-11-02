<?php

declare(strict_types=1);

use App\Models\Car;
use App\Models\LedgerEntry;
use App\Models\RaceRun;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WorkshopJob;
use App\Services\CityGarages;
use App\Services\Ledger;
use App\Services\MissionScale;
use App\Services\RunService;
use App\Services\Slots;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../../Support/MissionHelpers.php';

// The limit of active cars: a driver has two cars on the road (three more with the VIP account), the others stand in storage, where they do not drive, burn no fuel and wear
// nothing. Nothing is taken from a driver who had more cars before the limit. The numbers are the ones the rules ship with.

afterEach(fn () => Carbon::setTestNow());

/** A driver with this many cars on the road, each with a run that was started now. */
function slotDriver(int $cars = 2, ?int $funds = null): User
{
    Carbon::setTestNow(missionNoon());

    $driver = User::factory()->guest()->create();

    for ($index = 0; $index < $cars; $index++) {
        RaceRun::factory()->create(['user_id' => $driver->id, 'cursor_ms' => RunService::nowMs()]);
    }

    app(Ledger::class)->ensureWallet($driver->id);

    if ($funds !== null && $funds > 0) {
        app(Ledger::class)->post($driver->id, $funds, 'gift', "seed:{$driver->id}");
    }

    return $driver;
}

/** @return array<string, mixed> the answer of GET /cars */
function fleetOf(User $driver): array
{
    return test()->getJson('/api/v1/cars', bearerFor($driver))->assertOk()->json('data');
}

function runOfCar(int $carId): RaceRun
{
    return RaceRun::query()->where('car_id', $carId)->firstOrFail();
}

function carIds(User $driver): array
{
    return Car::query()->where('user_id', $driver->id)->orderBy('id')->pluck('id')->all();
}

describe('the cars a driver gets', function () {
    it('go on the road while there is a slot, and are given the number of it', function () {
        $driver = slotDriver(1);

        $car = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');

        expect(runOfCar($car->id)->stop_reason)->toBeNull()->and(runOfCar($car->id)->status)->toBe(RaceRun::STATUS_RUNNING)
            // The cars are put in order when one comes: the car that was there has the first number.
            ->and($car->slot_no)->toBe(2)
            ->and(Car::query()->where('user_id', $driver->id)->orderBy('id')->first()->slot_no)->toBe(1);

        $fleet = fleetOf($driver);

        expect(collect($fleet['cars'])->pluck('slot_no')->all())->toBe([1, 2])
            ->and($fleet['slots'])->toMatchArray(['limit' => 2, 'active' => 2, 'stored' => 0, 'free' => 0, 'base' => 2, 'vip' => 3, 'vip_active' => false, 'legacy' => 0]);
    });

    it('go to storage when every slot is taken: they stand where they are bought, and nothing drives', function () {
        $driver = slotDriver(2);
        fleetOf($driver);

        $car = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');
        $run = runOfCar($car->id);

        expect($run->stop_reason)->toBe(RaceRun::STOP_STORED)->and($run->status)->toBe(RaceRun::STATUS_STOPPED)->and($car->slot_no)->toBeNull();

        $fleet = fleetOf($driver);

        expect(collect($fleet['cars'])->firstWhere('id', $car->id))->toMatchArray(['stored' => true, 'slot_no' => null])
            ->and($fleet['slots'])->toMatchArray(['active' => 2, 'stored' => 1, 'free' => 0]);
    });

    it('all go on the road in a game that has no limit', function () {
        missionRules(fn (array $data): array => [...$data, 'slots' => [...$data['slots'], 'enabled' => false]]);
        $driver = slotDriver(3);

        $car = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');

        expect(runOfCar($car->id)->stop_reason)->toBeNull()
            ->and(fleetOf($driver)['slots'])->toBeNull();
    });
});

describe('a car in storage', function () {
    it('earns nothing, burns no fuel and wears nothing, however long it stands', function () {
        $driver = slotDriver(2);
        [$first, $second] = carIds($driver);
        fleetOf($driver);

        $this->postJson("/api/v1/cars/{$second}/store", [], bearerFor($driver))->assertOk()->assertJsonPath('repeated', false);

        $run = runOfCar($second)->refresh();
        $cash = Wallet::query()->find($driver->id)->cash_cents;
        $fuel = $run->fuel_ml;
        $laps = $run->laps_done;
        $mileage = Car::query()->find($second)->mileage_m;

        Carbon::setTestNow(Carbon::now()->addHours(6));
        $this->getJson('/api/v1/cars', bearerFor($driver))->assertOk();

        $run->refresh();

        expect($run->fuel_ml)->toBe($fuel)->and($run->laps_done)->toBe($laps)->and($run->stop_reason)->toBe(RaceRun::STOP_STORED)
            ->and(Car::query()->find($second)->mileage_m)->toBe($mileage)
            // The car on the road earned in the same hours.
            ->and(Wallet::query()->find($driver->id)->cash_cents)->toBeGreaterThan($cash)
            ->and(runOfCar($first)->laps_done)->toBeGreaterThan(0);
    });

    it('is not the best car of the driver when the missions are worked out', function () {
        $driver = slotDriver(2);
        [$first, $second] = carIds($driver);
        RaceRun::query()->where('car_id', $second)->update(['stop_reason' => RaceRun::STOP_STORED, 'status' => RaceRun::STATUS_STOPPED]);
        RaceRun::query()->where('car_id', $first)->update(['car_slug' => 'rdzak-gt-4']);

        expect(app(MissionScale::class)->tankful($driver->id))->not->toBeNull();

        RaceRun::query()->where('car_id', $first)->update(['stop_reason' => RaceRun::STOP_STORED]);

        expect(app(MissionScale::class)->tankful($driver->id))->toBeNull();
    });

    it('keeps its stop when the workshop is done with it, and is not put on the road by it', function () {
        $driver = slotDriver(2);
        [, $second] = carIds($driver);
        $run = runOfCar($second);
        $run->forceFill(['status' => RaceRun::STATUS_STOPPED, 'stop_reason' => RaceRun::STOP_STORED])->save();

        // The workshop puts a car in for a job and takes it out again; the car that stands for another reason keeps it.
        runService()->enterWorkshop($run, RunService::nowMs());
        expect($run->refresh()->stop_reason)->toBe(RaceRun::STOP_STORED);

        runService()->leaveWorkshop($run, dryInputs(), RunService::nowMs());

        expect($run->refresh()->stop_reason)->toBe(RaceRun::STOP_STORED)->and($run->status)->toBe(RaceRun::STATUS_STOPPED);
    });

    it('cannot be the car that the driver plays', function () {
        $driver = slotDriver(2);
        [$first, $second] = carIds($driver);
        fleetOf($driver);
        $this->postJson("/api/v1/cars/{$second}/store", [], bearerFor($driver))->assertOk();

        $this->postJson("/api/v1/cars/{$second}/select", [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'car_stored');
        // A selection of a car that was put in storage moves to a car that drives.
        $this->postJson("/api/v1/cars/{$first}/select", [], bearerFor($driver))->assertOk()->assertJsonPath('data.selected', $first);
    });
});

describe('putting a car in storage', function () {
    it('pays what it earned until now first, frees the slot, and moves the selection to a car that drives', function () {
        $driver = slotDriver(2, 5_000);
        [$first, $second] = carIds($driver);
        User::query()->whereKey($driver->id)->update(['selected_car_id' => $second]);
        fleetOf($driver);
        $cash = Wallet::query()->find($driver->id)->cash_cents;

        Carbon::setTestNow(Carbon::now()->addMinutes(20));
        $this->postJson("/api/v1/cars/{$second}/store", [], bearerFor($driver))->assertOk()
            ->assertJsonPath('data.selected', $first)->assertJsonPath('data.slots.active', 1)->assertJsonPath('data.slots.stored', 1)->assertJsonPath('data.slots.free', 1);

        expect(Wallet::query()->find($driver->id)->cash_cents)->toBeGreaterThan($cash)
            ->and(runOfCar($second)->stop_reason)->toBe(RaceRun::STOP_STORED)
            ->and(Car::query()->find($second)->slot_no)->toBeNull()
            ->and(Car::query()->find($first)->slot_no)->toBe(1);
    });

    it('keeps one car on the road: the last is not put in storage', function () {
        $driver = slotDriver(1);
        [$only] = carIds($driver);

        $this->postJson("/api/v1/cars/{$only}/store", [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'last_active_car');
    });

    it('does not put in storage a car that is on the road to another city or has work waiting in the workshop', function () {
        $driver = slotDriver(2);
        [$first, $second] = carIds($driver);
        WorkshopJob::factory()->create(['user_id' => $driver->id, 'car_id' => $second, 'kind' => WorkshopJob::KIND_SERVICE, 'status' => WorkshopJob::STATUS_QUEUED, 'start_ms' => RunService::nowMs() + 60_000, 'finish_ms' => RunService::nowMs() + 120_000]);

        $this->postJson("/api/v1/cars/{$second}/store", [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'car_busy');

        RaceRun::query()->where('car_id', $first)->update(['stop_reason' => RaceRun::STOP_TRAVEL, 'status' => RaceRun::STATUS_STOPPED]);
        $this->postJson("/api/v1/cars/{$first}/store", [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'car_busy');
    });

    it('is harmless when it is asked twice, and not for a car that is not the driver’s', function () {
        $driver = slotDriver(2);
        [, $second] = carIds($driver);
        $other = slotDriver(1);

        $this->postJson("/api/v1/cars/{$second}/store", [], bearerFor($driver))->assertOk()->assertJsonPath('repeated', false);
        $this->postJson("/api/v1/cars/{$second}/store", [], bearerFor($driver))->assertOk()->assertJsonPath('repeated', true);
        $this->postJson('/api/v1/cars/'.carIds($other)[0].'/store', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'car_not_found');
    });

    it('is refused in a game that has no limit', function () {
        missionRules(fn (array $data): array => [...$data, 'slots' => [...$data['slots'], 'enabled' => false]]);
        $driver = slotDriver(2);

        $this->postJson('/api/v1/cars/'.carIds($driver)[1].'/store', [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'slots_unavailable');
    });
});

describe('putting a car on the road', function () {
    it('needs a free slot, and is ready after the time of the rules', function () {
        $driver = slotDriver(2);
        fleetOf($driver);
        $stored = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');

        $this->postJson("/api/v1/cars/{$stored->id}/activate", [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'no_free_slot');

        $this->postJson('/api/v1/cars/'.carIds($driver)[1].'/store', [], bearerFor($driver))->assertOk();
        $this->postJson("/api/v1/cars/{$stored->id}/activate", [], bearerFor($driver))->assertOk()->assertJsonPath('repeated', false)->assertJsonPath('data.slots.active', 2);

        $run = runOfCar($stored->id);

        expect($run->stop_reason)->toBeNull()->and($run->status)->toBe(RaceRun::STATUS_RUNNING)
            ->and($run->cursor_ms)->toBe(RunService::nowMs() + 120_000);

        // It earns nothing before it is ready, and settles without a fuss.
        $this->getJson('/api/v1/cars', bearerFor($driver))->assertOk();
        expect(runOfCar($stored->id)->laps_done)->toBe(0);
        Carbon::setTestNow(Carbon::now()->addMinutes(10));
        $this->getJson('/api/v1/cars', bearerFor($driver))->assertOk();
        expect(runOfCar($stored->id)->laps_done)->toBeGreaterThan(0);
    });

    it('is harmless for a car that is on the road', function () {
        $driver = slotDriver(2);

        $this->postJson('/api/v1/cars/'.carIds($driver)[0].'/activate', [], bearerFor($driver))->assertOk()->assertJsonPath('repeated', true);
    });
});

describe('swapping', function () {
    it('puts one car in storage and the other on the road, and costs a part of a tank, once', function () {
        $driver = slotDriver(2, 100_000);
        [$first, $second] = carIds($driver);
        fleetOf($driver);
        $stored = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');
        $cash = Wallet::query()->find($driver->id)->cash_cents;

        $answer = $this->postJson("/api/v1/cars/{$stored->id}/swap", ['with' => $second, 'request_id' => 'swap-1'], bearerFor($driver))->assertOk()->assertJsonPath('repeated', false);
        $fee = $answer->json('fee_cents');

        expect($fee)->toBeGreaterThan(0)
            ->and(runOfCar($second)->stop_reason)->toBe(RaceRun::STOP_STORED)
            ->and(runOfCar($stored->id)->stop_reason)->toBeNull()
            ->and(Wallet::query()->find($driver->id)->cash_cents)->toBeLessThan($cash)
            ->and(LedgerEntry::query()->where('user_id', $driver->id)->where('reason', 'swap')->count())->toBe(1)
            ->and(collect(fleetOf($driver)['cars'])->pluck('slot_no', 'id')->all())->toBe([$first => 1, $second => null, $stored->id => 2]);

        $cashAfter = Wallet::query()->find($driver->id)->cash_cents;
        $this->postJson("/api/v1/cars/{$stored->id}/swap", ['with' => $second, 'request_id' => 'swap-1'], bearerFor($driver))->assertOk()->assertJsonPath('repeated', true);

        expect(Wallet::query()->find($driver->id)->cash_cents)->toBe($cashAfter);
    });

    it('changes nothing when the driver cannot pay', function () {
        $driver = slotDriver(2, 0);
        [, $second] = carIds($driver);
        fleetOf($driver);
        $stored = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');
        Wallet::query()->where('user_id', $driver->id)->update(['cash_cents' => 0]);

        $this->postJson("/api/v1/cars/{$stored->id}/swap", ['with' => $second, 'request_id' => 'swap-2'], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'insufficient_funds');

        expect(runOfCar($second)->stop_reason)->toBeNull()->and(runOfCar($stored->id)->stop_reason)->toBe(RaceRun::STOP_STORED);
    });

    it('wants a car that is in storage and a car that is on the road', function () {
        $driver = slotDriver(2, 100_000);
        [$first, $second] = carIds($driver);

        $this->postJson("/api/v1/cars/{$first}/swap", ['with' => $second, 'request_id' => 'a'], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'car_not_stored');

        fleetOf($driver);
        $stored = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');
        $this->postJson("/api/v1/cars/{$stored->id}/swap", ['with' => $stored->id, 'request_id' => 'b'], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'car_not_active');
    });
});

describe('the cars a driver had before the limit', function () {
    it('stay on the road: the driver keeps them until they put one in storage, and the slots only fall', function () {
        $driver = slotDriver(4);
        [$a, $b, $c, $d] = carIds($driver);

        $fleet = fleetOf($driver);

        expect($fleet['slots'])->toMatchArray(['limit' => 4, 'legacy' => 2, 'active' => 4, 'free' => 0]);

        // A new car has no slot.
        $new = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');
        expect(runOfCar($new->id)->stop_reason)->toBe(RaceRun::STOP_STORED);

        $this->postJson("/api/v1/cars/{$d}/store", [], bearerFor($driver))->assertOk()->assertJsonPath('data.slots.legacy', 1)->assertJsonPath('data.slots.limit', 3)->assertJsonPath('data.slots.free', 0);
        // The slot that was given up is not there for the car that was waiting.
        $this->postJson("/api/v1/cars/{$new->id}/activate", [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'no_free_slot');

        $this->postJson("/api/v1/cars/{$c}/store", [], bearerFor($driver))->assertOk()->assertJsonPath('data.slots.legacy', 0)->assertJsonPath('data.slots.limit', 2);
        expect(Wallet::query()->find($driver->id)->legacy_slots)->toBe(0)->and(collect(fleetOf($driver)['cars'])->whereNotNull('slot_no')->pluck('id')->all())->toBe([$a, $b]);
    });

    it('are worked out before the first car that the driver gets after the limit, not after it', function () {
        $driver = slotDriver(3);

        // Three cars on the road: the legacy slot is one, and the car that comes now goes to storage.
        $new = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');

        expect(runOfCar($new->id)->stop_reason)->toBe(RaceRun::STOP_STORED)->and(Wallet::query()->find($driver->id)->legacy_slots)->toBe(1);
    });

    it('are as many as the cars a driver with the VIP account has above what it lets them', function () {
        $driver = slotDriver(4);
        Wallet::query()->where('user_id', $driver->id)->update(['vip_until' => now()->addDays(3)]);

        // 2 + 3 slots hold the four cars: nothing is kept from before.
        expect(fleetOf($driver)['slots'])->toMatchArray(['vip_active' => true, 'legacy' => 0, 'limit' => 5, 'free' => 1]);
    });
});

describe('selling', function () {
    it('keeps a car on the road: the only car that drives is not sold, a car in storage is, and the rest are put in order', function () {
        $driver = slotDriver(2, 0);
        [$first, $second] = carIds($driver);
        fleetOf($driver);
        $stored = app(CityGarages::class)->park($driver->id, 'rdzak-gt-4', 'zlomowo');
        $this->postJson("/api/v1/cars/{$second}/store", [], bearerFor($driver))->assertOk();
        // Two cars are in storage, one on the road.
        $this->postJson("/api/v1/cars/{$first}/sell", [], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'last_active_car');
        $this->postJson("/api/v1/cars/{$second}/sell", [], bearerFor($driver))->assertOk();

        expect(collect(fleetOf($driver)['cars'])->pluck('id')->all())->toBe([$first, $stored->id]);

        $this->postJson("/api/v1/cars/{$stored->id}/activate", [], bearerFor($driver))->assertOk();
        $this->postJson("/api/v1/cars/{$first}/sell", [], bearerFor($driver))->assertOk()->assertJsonPath('data.selected', $stored->id);

        expect(Car::query()->find($stored->id)->slot_no)->toBe(1);
    });
});

describe('the limit', function () {
    it('is the base of the rules for a driver who has no account of the VIP, and more with it', function () {
        $driver = slotDriver(1, 0);

        expect(app(Slots::class)->limits($driver->id))->toMatchArray(['base' => 2, 'vip' => 3, 'vip_active' => false, 'total' => 2]);

        Wallet::query()->where('user_id', $driver->id)->update(['vip_until' => now()->addDay()]);

        expect(app(Slots::class)->limits($driver->id))->toMatchArray(['vip_active' => true, 'total' => 5]);

        Wallet::query()->where('user_id', $driver->id)->update(['vip_until' => now()->subDay()]);

        expect(app(Slots::class)->limits($driver->id)['total'])->toBe(2);
    });
});

describe('the slots of the VIP account', function () {
    /** Three cars on the road with a lot of fuel, and a VIP account that ends in half an hour. */
    function vipDriver(): User
    {
        $driver = slotDriver(3);
        RaceRun::query()->where('user_id', $driver->id)->update(['fuel_ml' => 450_000]);
        Wallet::query()->where('user_id', $driver->id)->update(['vip_until' => now()->addMinutes(30)]);
        // The cars are numbered while the account lasts: the third is in the slot it gives.
        fleetOf($driver);

        return $driver;
    }

    it('stop earning the moment the account ends, for a driver who is away when it does, and go to storage', function () {
        $driver = vipDriver();
        [$first, $second, $third] = carIds($driver);

        expect(fleetOf($driver)['slots'])->toMatchArray(['vip_active' => true, 'limit' => 5, 'active' => 3, 'free' => 2]);

        Carbon::setTestNow(Carbon::now()->addHours(2));
        $fleet = fleetOf($driver);

        // The third car earned the half hour of the account (24 laps of 75 s) and not the two hours the cars in the base slots did.
        expect(runOfCar($third)->stop_reason)->toBe(RaceRun::STOP_STORED)
            ->and(runOfCar($third)->laps_done)->toBe(24)
            ->and(runOfCar($first)->laps_done)->toBeGreaterThan(90)->and(runOfCar($second)->laps_done)->toBeGreaterThan(90)
            ->and(Car::query()->find($third)->slot_no)->toBeNull()
            ->and($fleet['slots'])->toMatchArray(['vip_active' => false, 'limit' => 2, 'active' => 2, 'stored' => 1, 'free' => 0])
            ->and(collect($fleet['cars'])->firstWhere('id', $third)['stored'])->toBeTrue();
    });

    it('keep the cars of the base slots, whichever of them the driver plays, and move the selection when the played car goes', function () {
        $driver = vipDriver();
        [, , $third] = carIds($driver);
        User::query()->whereKey($driver->id)->update(['selected_car_id' => $third]);

        Carbon::setTestNow(Carbon::now()->addHours(1));

        expect(fleetOf($driver)['selected'])->not->toBe($third)->and(runOfCar(carIds($driver)[0])->stop_reason)->toBeNull();
    });

    it('go on earning for as long as the account is extended, and are not put on the road again by themselves when it is bought again', function () {
        $driver = vipDriver();
        [, , $third] = carIds($driver);

        // The account is extended before it ends: the car is on the road for the new time.
        Wallet::query()->where('user_id', $driver->id)->update(['vip_until' => now()->addHours(3)]);
        Carbon::setTestNow(Carbon::now()->addHours(1));
        fleetOf($driver);

        expect(runOfCar($third)->stop_reason)->toBeNull()->and(runOfCar($third)->laps_done)->toBeGreaterThan(40);

        // It ends, and a new account does not put the car back.
        Carbon::setTestNow(Carbon::now()->addHours(3));
        fleetOf($driver);
        expect(runOfCar($third)->stop_reason)->toBe(RaceRun::STOP_STORED);

        Wallet::query()->where('user_id', $driver->id)->update(['vip_until' => now()->addDays(30)]);
        fleetOf($driver);

        expect(runOfCar($third)->stop_reason)->toBe(RaceRun::STOP_STORED);
        $this->postJson("/api/v1/cars/{$third}/activate", [], bearerFor($driver))->assertOk()->assertJsonPath('data.slots.active', 3);
    });

    it('are not an end for a driver who never had the account: the cars they have above the limit are theirs', function () {
        $driver = slotDriver(2);
        fleetOf($driver);
        // A third car that did not come through the rules of the limit (a gift of the people who run the game, say).
        RaceRun::factory()->create(['user_id' => $driver->id, 'cursor_ms' => RunService::nowMs()]);
        RaceRun::query()->where('user_id', $driver->id)->update(['fuel_ml' => 450_000]);

        Carbon::setTestNow(Carbon::now()->addHours(1));
        fleetOf($driver);

        expect(RaceRun::query()->where('user_id', $driver->id)->whereNull('stop_reason')->count())->toBe(3);
    });

    it('do not touch the cars a driver kept from before the limit', function () {
        $driver = slotDriver(4);
        RaceRun::query()->where('user_id', $driver->id)->update(['fuel_ml' => 450_000]);
        fleetOf($driver);

        Carbon::setTestNow(Carbon::now()->addHours(1));
        fleetOf($driver);

        expect(RaceRun::query()->where('user_id', $driver->id)->whereNull('stop_reason')->count())->toBe(4);
    });

    describe('the answers of a purchase', function () {
        it('say that the car went to storage when every slot was taken, and that it did not when there was one', function () {
            $driver = slotDriver(1, 5_000_000);
            // The garage of the home city has room for a second car and a third.
            $this->postJson('/api/v1/cars', ['vehicle' => 'rdzak-gt-4', 'city' => 'zlomowo', 'request_id' => 'buy-1'], bearerFor($driver))->assertCreated()->assertJsonPath('stored', false);
            $this->postJson('/api/v1/cars', ['vehicle' => 'rdzak-gt-4', 'city' => 'zlomowo', 'request_id' => 'buy-2'], bearerFor($driver))->assertCreated()->assertJsonPath('stored', true);

            $fleet = fleetOf($driver);

            expect($fleet['slots'])->toMatchArray(['active' => 2, 'stored' => 1])
                ->and(collect($fleet['cars'])->where('stored', true))->toHaveCount(1);
        });
    });
});
