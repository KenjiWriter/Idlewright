<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Exceptions\RuleRefusedException;
use App\Models\Car;
use App\Models\RaceRun;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The cars a driver owns. Every car has a run of its own, which drives on the track of the city the car stands in, and the driver
 * plays one of them at a time: the one they selected, or the first when none was. The screens show that one.
 */
final class Garage
{
    public function __construct(private readonly Rules $rules) {}

    /**
     * The car the driver plays: the one they selected, or the first car they have that is on the road (a car in storage is not played: it does not drive), or, when
     * none is, the first.
     */
    public function carOf(int $userId): ?Car
    {
        $stored = $this->storedCarIds($userId);
        $selected = User::query()->whereKey($userId)->value('selected_car_id');

        if ($selected !== null && ! in_array((int) $selected, $stored, true)) {
            $car = Car::query()->where('user_id', $userId)->find($selected);

            if ($car !== null) {
                return $car;
            }
        }

        return Car::query()->where('user_id', $userId)->whereNotIn('id', $stored)->orderBy('id')->first()
            ?? Car::query()->where('user_id', $userId)->orderBy('id')->first();
    }

    /** Whether a car stands in storage (see `Slots`): the car a driver has just got when every slot was taken. */
    public function isStored(?Car $car): bool
    {
        return $car !== null && $this->runOf($car)?->stop_reason === RaceRun::STOP_STORED;
    }

    /** @return list<int> the cars of the driver that are in storage (see `Slots`) */
    public function storedCarIds(int $userId): array
    {
        return array_map('intval', RaceRun::query()->where('user_id', $userId)->where('stop_reason', RaceRun::STOP_STORED)->whereNotNull('car_id')->pluck('car_id')->all());
    }

    /** @return Collection<int, Car> every car of the driver, the oldest first */
    public function cars(int $userId): Collection
    {
        return Car::query()->where('user_id', $userId)->orderBy('id')->get();
    }

    /**
     * The driver plays another of their cars.
     *
     * @throws RuleRefusedException the car is not the driver's, or it is in storage (`car_stored`: it has to be put on the road first)
     */
    public function select(int $userId, int $carId): Car
    {
        $car = Car::query()->where('user_id', $userId)->find($carId) ?? throw new RuleRefusedException('car_not_found');

        if (in_array($car->id, $this->storedCarIds($userId), true)) {
            throw new RuleRefusedException('car_stored');
        }

        User::query()->whereKey($userId)->update(['selected_car_id' => $car->id]);

        return $car;
    }

    /** The run of a car: the one that drives it now. */
    public function runOf(Car $car): ?RaceRun
    {
        return RaceRun::query()->where('car_id', $car->id)->orderByDesc('id')->first();
    }

    /** The run of the car the driver plays. */
    public function selectedRun(int $userId): ?RaceRun
    {
        $car = $this->carOf($userId);

        return $car === null ? null : $this->runOf($car);
    }

    /** The city a run is in: the region of the track it drives on. */
    public function cityOf(RaceRun $run): string
    {
        return (string) ($this->rules->track($run->track_slug)['region'] ?? $this->rules->homeRegion());
    }
}
