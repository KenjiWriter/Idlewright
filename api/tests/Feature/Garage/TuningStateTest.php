<?php

declare(strict_types=1);

use App\Domain\Run\Rng;
use App\Http\Resources\PartResource;
use App\Models\Car;
use App\Models\Part;
use App\Models\User;
use App\Services\AccountExport;
use App\Services\Ledger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

afterEach(fn () => Carbon::setTestNow());

/** The bonuses of a part in two places: a gravel bonus at 6.2% after seven tries, and a rain bonus at 3.1% after two. The third place is empty. */
function tunedPlaces(): array
{
    return [
        ['key' => 'gravel-grip', 'ppm' => 62_000, 'tries' => 7, 'fails' => 3],
        ['key' => 'rain-handling', 'ppm' => 31_000, 'tries' => 2, 'fails' => 0],
        null,
    ];
}

/** A key of the application with which the first merge of the driver is a success. */
function tuningMergeKey(int $userId): string
{
    for ($i = 0; $i < 1_000; $i++) {
        if (Rng::roll(hash_hmac('sha256', "merge|{$userId}|0", "tuning-merge-key-{$i}"), 'merge', 0) < 800_000) {
            return "tuning-merge-key-{$i}";
        }
    }

    throw new RuntimeException('No key gives a success.');
}

describe('the bonuses that a part holds', function () {
    it('are kept in a column of the part, as a list with one entry for each place', function () {
        expect(Schema::hasColumn('parts', 'tuning'))->toBeTrue();

        $part = Part::factory()->create(['tuning' => tunedPlaces()]);

        expect($part->refresh()->tuning)->toBe(tunedPlaces())
            ->and(Part::factory()->create()->refresh()->tuning)->toBeNull();
    });

    it('are shown to the client with their names in the language of the driver, as many places as the part has', function () {
        [$driver] = driverWithRun();
        $tuned = Part::factory()->create(['user_id' => $driver->id, 'tuning' => tunedPlaces()]);
        $plain = Part::factory()->create(['user_id' => $driver->id]);

        $inventory = collect(tuningGarage($driver, ['X-Locale' => 'pl'])->assertOk()->json('data.inventory'))->keyBy('id');

        expect($inventory[$tuned->id]['tuning_slots'])->toBe(3)
            ->and($inventory[$tuned->id]['tuning'])->toHaveCount(3)
            ->and($inventory[$tuned->id]['tuning'][0])->toMatchArray([
                'place' => 1, 'key' => 'gravel-grip', 'name' => 'Przyczepność na żwirze', 'effect' => 'surface', 'stat' => null,
                'value_ppm' => 62_000, 'percent' => 6.2, 'tries' => 7,
                'context' => ['kinds' => null, 'surfaces' => ['gravel_dry', 'mud'], 'weathers' => null],
            ])
            ->and($inventory[$tuned->id]['tuning'][0]['description'])->toBe('Lepsza przyczepność opon na żwirze i błocie.')
            ->and($inventory[$tuned->id]['tuning'][1])->toMatchArray(['place' => 2, 'key' => 'rain-handling', 'effect' => 'stat', 'stat' => 'handling', 'percent' => 3.1])
            ->and($inventory[$tuned->id]['tuning'][2])->toBeNull()
            // A part that was never tuned has all its places empty.
            ->and($inventory[$plain->id]['tuning'])->toBe([null, null, null]);
    });

    it('show a place that holds a bonus the pack does not know as an empty one, and never show more places than the part has', function () {
        $part = Part::factory()->create(['tuning' => [['key' => 'vanished', 'ppm' => 50_000, 'tries' => 1, 'fails' => 0], ['key' => 'dry-grip', 'ppm' => 40_000, 'tries' => 1, 'fails' => 0], null, ['key' => 'snow-grip', 'ppm' => 40_000, 'tries' => 1, 'fails' => 0]]]);

        $shown = PartResource::make($part)->resolve();

        expect($shown['tuning'])->toHaveCount(3)
            ->and($shown['tuning'][0])->toBeNull()
            ->and($shown['tuning'][1]['key'])->toBe('dry-grip')
            ->and($shown['tuning'][2])->toBeNull();
    });

    it('go with a part onto a car, where the pipeline reads them', function () {
        [, $run] = driverWithRun();
        $car = Car::query()->findOrFail($run->car_id);
        Part::factory()->state(['slot' => 'tires', 'part_slug' => 'street-tires-t2', 'tuning' => tunedPlaces()])->mountedOn($car)->create();

        expect($car->mountedParts()['tires']['tuning'])->toBe(tunedPlaces());
    });

    it('stay with the part that a merge keeps, and go with the one that it uses up', function () {
        [$driver] = driverWithRun(['seed' => str_repeat('0', 32)]);
        (new Ledger)->post($driver->id, 100_000, 'gift', "tuning-merge:{$driver->id}");
        config(['app.key' => tuningMergeKey($driver->id)]);
        $base = Part::factory()->create(['user_id' => $driver->id, 'slot' => 'tires', 'part_slug' => 'street-tires-t2', 'tuning' => tunedPlaces()]);
        $other = Part::factory()->create(['user_id' => $driver->id, 'slot' => 'tires', 'part_slug' => 'street-tires-t2', 'tuning' => [['key' => 'dry-grip', 'ppm' => 90_000, 'tries' => 9, 'fails' => 0], null, null]]);

        test()->postJson('/api/v1/garage/jobs', ['kind' => 'merge', 'part' => $base->id, 'with' => $other->id], bearerFor($driver))->assertCreated();
        clockAt(900_000);
        tuningGarage($driver)->assertOk();

        $merged = Part::query()->find($base->id);

        // Two common tires made one uncommon one that keeps what the base had: the bonuses of the other are gone with it.
        expect($merged->rarity)->toBe(2)->and($merged->tuning)->toBe(tunedPlaces())->and(Part::query()->find($other->id))->toBeNull();
    });

    it('are in the copy of the account that the driver may ask for', function () {
        $user = accountWithHistory();
        Part::query()->where('user_id', $user->id)->whereNull('car_id')->update(['tuning' => json_encode(tunedPlaces())]);

        $inventory = (new AccountExport)->for($user)['game']['inventory'];

        expect($inventory[0]['tuning'])->toBe(tunedPlaces());
    });
});

function tuningGarage(User $driver, array $headers = []): TestResponse
{
    return test()->getJson('/api/v1/garage', [...bearerFor($driver), ...$headers]);
}
