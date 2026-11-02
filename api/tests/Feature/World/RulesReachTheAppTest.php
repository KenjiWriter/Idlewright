<?php

declare(strict_types=1);

use App\Content\Rules;
use App\Http\Resources\PartResource;
use App\Models\Part;

// Two rules that the app used to know as numbers of its own: what the odometer of a new car of the dealer says, and the line under which a part is unserviceable. Now the
// server says them, so a game with other rules shows its own.

/** The rules of the game with one change in the wear or the market. */
function rulesWith(callable $change): void
{
    app()->instance(Rules::class, Rules::fromSource(contentWith(['rules/starter' => $change])));
}

it('tells the app what the odometer of a new car of the dealer says', function () {
    [$driver] = driverWithRun();

    $this->getJson('/api/v1/market', bearerFor($driver))->assertOk()->assertJsonPath('data.dealer_mileage_km', 10);
});

it('tells the app the mileage of the dealer of the game, whatever it is', function () {
    rulesWith(function (array $rules): array {
        $rules['market']['dealer_mileage_km'] = 3;

        return $rules;
    });
    [$driver] = driverWithRun();

    $this->getJson('/api/v1/market', bearerFor($driver))->assertOk()->assertJsonPath('data.dealer_mileage_km', 3);
});

it('says that a part is unserviceable by the line of the rules, a fifth of its life now', function () {
    [$driver] = driverWithRun();
    $worn = Part::factory()->create(['user_id' => $driver->id, 'part_slug' => 'street-tires-t2', 'slot' => 'tires', 'wear_ppb' => 850_000_000]);
    $half = Part::factory()->create(['user_id' => $driver->id, 'part_slug' => 'street-tires-t2', 'slot' => 'tires', 'wear_ppb' => 700_000_000]);

    expect((new PartResource($worn))->toArray(request())['unserviceable'])->toBeTrue()
        ->and((new PartResource($half))->toArray(request())['unserviceable'])->toBeFalse();

    // A game that puts the line at 40% calls the second one unserviceable too, and the app has no number of its own to say otherwise.
    rulesWith(function (array $rules): array {
        $rules['wear']['unserviceable_ppm'] = 400_000;

        return $rules;
    });

    expect((new PartResource($half))->toArray(request())['unserviceable'])->toBeTrue();
});
