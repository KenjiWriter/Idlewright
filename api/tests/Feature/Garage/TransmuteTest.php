<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Domain\Run\Rarity;
use App\Models\Car;
use App\Models\LedgerEntry;
use App\Models\Part;
use App\Models\PartTransmutation;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WorkshopJob;
use App\Services\Ledger;
use App\Services\Premium;
use App\Services\RunService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

// Transmutation of the rarity of a part: a part of the inventory is made one rarity better for cash and premium coins, at once, with the affixes it would have by any other way (so
// the result is shown before it is paid), and is marked, so that it is not used to merge. Street tires of tier 2 cost $570: the first step costs 0.8 of that in cash ($456) and 3 times
// 1.3 coins (4).

afterEach(fn () => Carbon::setTestNow());

/** A driver at the level that opens transmutation, with a car that drives, some cash and some coins. */
function transmuteDriver(int $cash = 500_000, int $coins = 100): User
{
    [$driver] = driverWithRun(['seed' => str_repeat('0', 32)]);
    $ledger = app(Ledger::class);
    $ledger->post($driver->id, $cash, 'gift', "transmute-cash-seed:{$driver->id}");
    $ledger->grantXp($driver->id, app(Rules::class)->playerXpToReach(6));

    if ($coins > 0) {
        app(Premium::class)->grant($driver->id, $coins, 'gift', "transmute-coins-seed:{$driver->id}");
    }

    return $driver;
}

function transmuteStock(User $driver, array $attributes = []): Part
{
    return Part::factory()->create(['user_id' => $driver->id, 'part_slug' => 'street-tires-t2', 'slot' => 'tires', ...$attributes]);
}

function transmuteCall(User $driver, int $part, string $requestId = 'req-1'): TestResponse
{
    return test()->postJson("/api/v1/parts/{$part}/transmute", ['request_id' => $requestId], bearerFor($driver));
}

function walletCoins(User $driver): int
{
    return (int) Wallet::query()->find($driver->id)->premium_coins;
}

function walletCash(User $driver): int
{
    return (int) Wallet::query()->find($driver->id)->cash_cents;
}

/** The rules of the game with a change made to the transmutation. */
function theTransmutationIs(array $change): void
{
    $data = json_decode((string) file_get_contents(config('ride.content_path').'/rules/starter.json'), true, 512, JSON_THROW_ON_ERROR);
    $data['transmute'] = [...$data['transmute'], ...$change];
    app()->singleton(Rules::class, fn () => Rules::fromArray($data));
}

describe('what a transmutation would be', function () {
    it('is told for every part of the inventory in the garage, with what it costs and why it cannot be done now', function () {
        $driver = transmuteDriver();
        $common = transmuteStock($driver);
        $epic = transmuteStock($driver, ['rarity' => Rarity::Epic->value]);
        $legendary = transmuteStock($driver, ['rarity' => Rarity::Legendary->value]);

        $inventory = collect($this->getJson('/api/v1/garage', bearerFor($driver))->assertOk()->json('data.inventory'))->keyBy('id');

        expect($inventory[$common->id]['transmute'])->toBe(['to' => 'uncommon', 'coins' => 4, 'cash_cents' => 45_600, 'blocked' => null])
            ->and($inventory[$epic->id]['transmute'])->toBeNull()
            ->and($inventory[$legendary->id]['transmute'])->toBeNull();
    });

    it('says that the level is not there yet, and that the part waits in the workshop', function () {
        $driver = transmuteDriver();
        $part = transmuteStock($driver);
        $other = transmuteStock($driver);
        $before = fn (int $id) => collect($this->getJson('/api/v1/garage', bearerFor($driver))->json('data.inventory'))->firstWhere('id', $id)['transmute']['blocked'];

        WorkshopJob::factory()->create(['user_id' => $driver->id, 'part_id' => $part->id, 'car_id' => null, 'kind' => WorkshopJob::KIND_MERGE, 'merge_part_id' => $other->id, 'status' => WorkshopJob::STATUS_QUEUED, 'start_ms' => RunService::nowMs() + 60_000, 'finish_ms' => RunService::nowMs() + 3_600_000]);

        expect($before($part->id))->toBe('busy');

        $low = User::factory()->guest()->create();
        $mine = transmuteStock($low);

        expect(collect($this->getJson('/api/v1/garage', bearerFor($low))->json('data.inventory'))->firstWhere('id', $mine->id)['transmute']['blocked'])->toBe('level');
    });

    it('shows the part as it would be, with the affixes it gets, and they are the ones it gets', function () {
        $driver = transmuteDriver();
        $part = transmuteStock($driver, ['upgrade_level' => 2]);

        $preview = $this->getJson("/api/v1/parts/{$part->id}/transmute", bearerFor($driver))->assertOk()->assertJsonPath('data.to', 'uncommon')->assertJsonPath('data.coins', 4)->json('data');

        expect(Part::query()->find($part->id)->rarity)->toBe(Rarity::Common->value)
            ->and($preview['after']['rarity'])->toBe('uncommon')
            ->and($preview['after']['affixes'])->toHaveCount(1);

        transmuteCall($driver, $part->id)->assertCreated()->assertJsonPath('data.rarity', 'uncommon');

        $after = $this->getJson('/api/v1/garage', bearerFor($driver))->json('data.inventory.0');

        expect($after['affixes'])->toBe($preview['after']['affixes']);
    });
});

describe('transmuting a part', function () {
    it('makes it one rarity better for the coins and the cash of the step, once, and writes it down', function () {
        $driver = transmuteDriver();
        $part = transmuteStock($driver);
        $cash = walletCash($driver);
        $coins = walletCoins($driver);

        transmuteCall($driver, $part->id)->assertCreated()->assertJsonPath('repeated', false)->assertJsonPath('coins', 4)->assertJsonPath('cash_cents', 45_600);

        $part->refresh();

        expect($part->rarity)->toBe(Rarity::Uncommon->value)->and($part->wasTransmuted())->toBeTrue()->and($part->affixes)->toHaveCount(1)
            ->and(walletCash($driver))->toBe($cash - 45_600)->and(walletCoins($driver))->toBe($coins - 4)
            ->and(LedgerEntry::query()->where('user_id', $driver->id)->where('reason', 'transmute')->count())->toBe(2)
            ->and(PartTransmutation::query()->where('user_id', $driver->id)->first()->toArray())->toMatchArray(['part_id' => $part->id, 'from_rarity' => 1, 'to_rarity' => 2, 'coins' => 4, 'cash_cents' => 45_600]);
    });

    it('charges nothing and changes nothing when the request comes a second time', function () {
        $driver = transmuteDriver();
        $part = transmuteStock($driver);

        transmuteCall($driver, $part->id, 'same')->assertCreated();
        $cash = walletCash($driver);
        $coins = walletCoins($driver);

        transmuteCall($driver, $part->id, 'same')->assertOk()->assertJsonPath('repeated', true)->assertJsonPath('coins', 0);

        expect(walletCash($driver))->toBe($cash)->and(walletCoins($driver))->toBe($coins)->and(Part::query()->find($part->id)->rarity)->toBe(Rarity::Uncommon->value);
    });

    it('keeps what the part is: its model, its upgrade, its wear, what it was tuned with and the affixes it had', function () {
        $driver = transmuteDriver();
        $tuning = [['key' => 'grip-wet', 'ppm' => 30_000]];
        $part = transmuteStock($driver, ['rarity' => Rarity::Uncommon->value, 'upgrade_level' => 3, 'wear_ppb' => 120_000_000, 'tuning' => $tuning, 'affixes' => ['grip' => 31_000]]);

        transmuteCall($driver, $part->id)->assertCreated()->assertJsonPath('coins', 10)->assertJsonPath('cash_cents', 159_600);

        $part->refresh();

        expect($part->rarity)->toBe(Rarity::Rare->value)->and($part->part_slug)->toBe('street-tires-t2')->and($part->upgrade_level)->toBe(3)->and($part->wear_ppb)->toBe(120_000_000)
            ->and($part->tuning)->toBe($tuning)->and($part->affixes['grip'])->toBe(31_000)->and(count($part->affixes))->toBeGreaterThanOrEqual(1);
    });

    it('is refused for a part that cannot be made better, a part that is on a car, one that waits in the workshop and one that is not the driver’s', function () {
        $driver = transmuteDriver();

        transmuteCall($driver, transmuteStock($driver, ['rarity' => Rarity::Epic->value])->id)->assertStatus(422)->assertJsonPath('code', 'transmute_max');
        transmuteCall($driver, transmuteStock($driver, ['rarity' => Rarity::Legendary->value])->id)->assertStatus(422)->assertJsonPath('code', 'transmute_max');

        $mounted = Part::factory()->mountedOn(Car::query()->where('user_id', $driver->id)->first())->create(['user_id' => $driver->id]);
        transmuteCall($driver, $mounted->id)->assertStatus(422)->assertJsonPath('code', 'part_mounted');

        $waiting = transmuteStock($driver);
        WorkshopJob::factory()->create(['user_id' => $driver->id, 'part_id' => $waiting->id, 'car_id' => null, 'kind' => WorkshopJob::KIND_MERGE, 'merge_part_id' => transmuteStock($driver)->id, 'status' => WorkshopJob::STATUS_QUEUED, 'start_ms' => RunService::nowMs() + 60_000, 'finish_ms' => RunService::nowMs() + 3_600_000]);
        transmuteCall($driver, $waiting->id)->assertStatus(422)->assertJsonPath('code', 'part_busy');

        transmuteCall($driver, transmuteStock(transmuteDriver())->id)->assertStatus(422)->assertJsonPath('code', 'part_not_found');
    });

    it('is refused below the level that opens it', function () {
        $driver = User::factory()->guest()->create();
        $part = transmuteStock($driver);

        transmuteCall($driver, $part->id)->assertStatus(422)->assertJsonPath('code', 'transmute_level');
    });

    it('changes nothing when the driver has not the coins or not the cash', function () {
        $poor = transmuteDriver(cash: 500_000, coins: 3);
        $part = transmuteStock($poor);

        transmuteCall($poor, $part->id)->assertStatus(422)->assertJsonPath('code', 'insufficient_premium');
        expect(Part::query()->find($part->id)->rarity)->toBe(Rarity::Common->value)->and(walletCoins($poor))->toBe(3)->and(PartTransmutation::query()->count())->toBe(0);

        $broke = transmuteDriver(cash: 1_000, coins: 100);
        $other = transmuteStock($broke);

        transmuteCall($broke, $other->id)->assertStatus(422)->assertJsonPath('code', 'insufficient_funds');
        // The coins that were taken before the cash was found short are given back with the rest.
        expect(walletCoins($broke))->toBe(100)->and(Part::query()->find($other->id)->wasTransmuted())->toBeFalse();
    });

    it('stops at the guard of a day', function () {
        theTransmutationIs(['limit_per_day' => 1]);
        $driver = transmuteDriver();

        transmuteCall($driver, transmuteStock($driver)->id, 'a')->assertCreated();
        transmuteCall($driver, transmuteStock($driver)->id, 'b')->assertStatus(422)->assertJsonPath('code', 'transmute_limit');

        Carbon::setTestNow(Carbon::now()->addDay()->addMinute());
        transmuteCall($driver, transmuteStock($driver)->id, 'c')->assertCreated();
    });

    it('costs cash only when the rules do not ask for coins, and is not there when the game has none', function () {
        theTransmutationIs(['coins_enabled' => false]);
        $driver = transmuteDriver(coins: 0);

        transmuteCall($driver, transmuteStock($driver)->id)->assertCreated()->assertJsonPath('coins', 0)->assertJsonPath('cash_cents', 45_600);

    });

    it('is not there when the game has none: no quote, and the call is refused', function () {
        theTransmutationIs(['enabled' => false]);
        $other = transmuteDriver();

        transmuteCall($other, transmuteStock($other)->id)->assertStatus(422)->assertJsonPath('code', 'transmute_unavailable');
        expect(collect($this->getJson('/api/v1/garage', bearerFor($other))->json('data.inventory'))->pluck('transmute')->filter()->all())->toBe([]);
    });
});

describe('a transmuted part', function () {
    /** A transmuted uncommon part and another uncommon one of the same kind, in the inventory of a driver who has the cash to merge. */
    function transmutedPair(): array
    {
        $driver = transmuteDriver();
        $first = transmuteStock($driver);
        transmuteCall($driver, $first->id)->assertCreated();
        $second = transmuteStock($driver, ['rarity' => Rarity::Uncommon->value]);

        return [$driver, $first->refresh(), $second];
    }

    it('is not used to merge: it has no quote of merging, and a job of merging with it is refused', function () {
        [$driver, $first, $second] = transmutedPair();

        $inventory = collect($this->getJson('/api/v1/garage', bearerFor($driver))->json('data.inventory'))->keyBy('id');

        // The transmuted part has no quote, and the other one has nobody to merge with.
        expect($inventory[$first->id]['merge'])->toBeNull()->and($inventory[$second->id]['merge']['with'])->toBe([]);

        $this->postJson('/api/v1/garage/jobs', ['kind' => 'merge', 'part' => $second->id, 'with' => $first->id], bearerFor($driver))->assertStatus(422)->assertJsonPath('code', 'part_transmuted');
    });

    it('can be used to merge when the rules say so', function () {
        theTransmutationIs(['merge_input' => true]);
        [$driver, $first, $second] = transmutedPair();

        $this->postJson('/api/v1/garage/jobs', ['kind' => 'merge', 'part' => $second->id, 'with' => $first->id], bearerFor($driver))->assertCreated();
    });

    it('can be transmuted again, up to the best rarity of the rules', function () {
        $driver = transmuteDriver(cash: 5_000_000);
        $part = transmuteStock($driver);

        foreach (['uncommon', 'rare', 'epic'] as $index => $to) {
            transmuteCall($driver, $part->id, "step-{$index}")->assertCreated()->assertJsonPath('data.rarity', $to);
        }

        transmuteCall($driver, $part->id, 'step-3')->assertStatus(422)->assertJsonPath('code', 'transmute_max');
    });
});
