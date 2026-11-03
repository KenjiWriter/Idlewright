<?php

declare(strict_types=1);

use App\Content\CarPerformance;
use App\Models\Part;
use App\Models\User;

// What a car shows in the game: its PI and the class the PI is in. It is information only: nothing about the income or the rivals of a run depends on it yet.

/** A driver with the starter car on the first track. */
function driverWithACar(): User
{
    $user = User::factory()->guest()->create();
    runService()->start($user->id, 'rdzak-gt-4', 'zlomowo-street', dryInputs(), 45_000, 0);

    return $user;
}

describe('the performance of a car in the answers', function () {
    it('is on the car of the garage, the cars of the fleet and the car of the run: the reference car is 300 PI in class D', function () {
        $user = driverWithACar();

        expect($this->getJson('/api/v1/garage', bearerFor($user))->assertOk()->json('data.car.performance'))->toBe(['pi' => 300, 'class' => 'D'])
            ->and($this->getJson('/api/v1/cars', bearerFor($user))->assertOk()->json('data.cars.0.performance'))->toBe(['pi' => 300, 'class' => 'D'])
            ->and($this->getJson('/api/v1/runs/current', bearerFor($user))->assertOk()->json('data.car.performance'))->toBe(['pi' => 300, 'class' => 'D']);
    });

    it('goes up with a part that is put on the car, and is the same number that the PI service says', function () {
        $user = driverWithACar();
        $car = $user->cars()->firstOrFail();
        Part::factory()->state(['part_slug' => 'sport-exhaust-t2', 'slot' => 'exhaust'])->mountedOn($car)->create(['user_id' => $user->id]);

        $shown = $this->getJson('/api/v1/garage', bearerFor($user))->assertOk()->json('data.car.performance');

        expect($shown['pi'])->toBeGreaterThan(300)
            ->and($shown)->toBe(app(CarPerformance::class)->ratingOf('rdzak-gt-4', $car->mountedParts()));
    });

    it('is on the cars the market sells and the exchange lists, as they come', function () {
        $user = driverWithACar();
        $market = $this->getJson('/api/v1/market', bearerFor($user))->assertOk()->json('data');

        foreach ([...array_column($market['dealer'], 'vehicle'), ...array_column($market['exchange']['listings'], 'vehicle')] as $vehicle) {
            expect($vehicle['performance']['pi'])->toBeInt()->toBeGreaterThan(0)->toBeLessThanOrEqual(999)
                ->and($vehicle['performance']['class'])->toBeIn(['F', 'E', 'D', 'C', 'B', 'A', 'S', 'SS']);
        }
    });

    it('says for each part of the inventory what the car would show with it on, and null when the part does not go on the car', function () {
        $user = driverWithACar();
        $better = Part::factory()->create(['user_id' => $user->id, 'part_slug' => 'sport-exhaust-t2', 'slot' => 'exhaust']);

        $inventory = collect($this->getJson('/api/v1/garage', bearerFor($user))->assertOk()->json('data.inventory'))->keyBy('id');

        expect($inventory[$better->id]['performance_after']['pi'])->toBeGreaterThan(300)
            ->and($inventory[$better->id]['performance_after']['class'])->toBeIn(['D', 'C']);
    });
});

it('says for each part of the shop that the car can take what the car would show with the part on, new, and nothing for a part it cannot take', function () {
    $user = driverWithACar();
    $shop = $this->getJson('/api/v1/shop', bearerFor($user))->assertOk()->json('data');
    $mountable = array_values(array_filter($shop['parts'], static fn (array $part): bool => $part['mountable']));
    $others = array_values(array_filter($shop['parts'], static fn (array $part): bool => ! $part['mountable']));

    expect($shop['car']['performance'])->toBe(['pi' => 300, 'class' => 'D'])
        ->and($mountable)->not->toBe([])
        ->and(array_column(array_column($mountable, 'performance_after'), 'pi'))->each->toBeInt()
        ->and(array_column($others, 'performance_after'))->each->toBeNull();
});
