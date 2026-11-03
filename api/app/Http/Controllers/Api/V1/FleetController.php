<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Content\CarPerformance;
use App\Content\Catalog;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\RaceRun;
use App\Services\CarSales;
use App\Services\CityGarages;
use App\Services\ContentImages;
use App\Services\Garage;
use App\Services\Gigs;
use App\Services\Ledger;
use App\Services\RunService;
use App\Services\Slots;
use App\Services\Travel;
use App\Services\Workshop;
use App\Support\I18nText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The cars of the driver and the garages they stand in: every car has a run of its own, drives on the track of its city by itself, and the
 * driver plays one of them, the one they select. A wreck from the yard is bought into the garage of a city, and a garage is bought in a
 * city the level of the driver opens.
 */
final class FleetController extends Controller
{
    public function __construct(
        private readonly Garage $garage,
        private readonly CityGarages $garages,
        private readonly Workshop $workshop,
        private readonly Travel $travel,
        private readonly Gigs $gigs,
        private readonly CarSales $sales,
        private readonly RunService $runs,
        private readonly Ledger $ledger,
        private readonly Rules $rules,
        private readonly Catalog $catalog,
        private readonly RunInputsFactory $inputs,
        private readonly Slots $slots,
        private readonly CarPerformance $performance,
    ) {}

    /** The cars and the garages as they are now: what is due is carried out first, and every car is settled, so the laps and the cash are current. */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $this->bringUpToDate($userId);

        return $this->respond($userId);
    }

    /**
     * Buys a wreck from the yard into the garage of a city: `vehicle` is the slug of a car the yard sells, `city` the slug of a city where the
     * driver has a garage with room, `request_id` makes a repeated request harmless (it buys nothing twice, and `repeated` says so).
     */
    public function buyCar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle' => ['required', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:64'],
            'request_id' => ['required', 'string', 'max:'.RunService::MAX_REQUEST_ID_LENGTH],
        ]);

        $userId = (int) $request->user()->getAuthIdentifier();
        $this->bringUpToDate($userId);
        $car = $this->garages->buyCar($userId, $data['vehicle'], $data['city'], $data['request_id']);

        return $this->respond($userId, $car === null ? 200 : 201, ['repeated' => $car === null, 'bought' => $car?->id, 'stored' => $this->garage->isStored($car)]);
    }

    /** Buys a garage in a city the level of the driver opens. */
    public function buyGarage(Request $request): JsonResponse
    {
        $data = $request->validate(['city' => ['required', 'string', 'max:64']]);

        $userId = (int) $request->user()->getAuthIdentifier();
        $this->garages->buy($userId, $data['city']);

        return $this->respond($userId, 201);
    }

    /** The driver plays another of their cars: the garage, the workshop and the race screen show it from now on. */
    public function select(Request $request, int $car): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $this->garage->select($userId, $car);
        $this->bringUpToDate($userId);

        return $this->respond($userId);
    }

    /**
     * Puts a car of the driver in storage: it stops where it stands, drives no more and burns no fuel, and its slot is free. What it earned until now is paid first.
     * A car in storage already stays there (`repeated`).
     */
    public function store(Request $request, int $car): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $this->workshop->advance($userId);
        $done = $this->slots->store($userId, $car);

        return $this->respond($userId, 200, ['repeated' => $done['repeated']]);
    }

    /** Puts a car from storage on the road, in a free slot: it drives when it is ready (`slots.swap_ms`). A car on the road already stays on it (`repeated`). */
    public function activate(Request $request, int $car): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $this->workshop->advance($userId);
        $done = $this->slots->activate($userId, $car);

        return $this->respond($userId, 200, ['repeated' => $done['repeated']]);
    }

    /**
     * Swaps the car on the road named by `with` for this one from storage: the first goes to storage, this one to the road, and the swap costs a part of a tank of the best
     * car of the driver. `request_id` makes a repeated request harmless: it changes and charges nothing (`repeated`).
     */
    public function swap(Request $request, int $car): JsonResponse
    {
        $data = $request->validate([
            'with' => ['required', 'integer'],
            'request_id' => ['required', 'string', 'max:'.RunService::MAX_REQUEST_ID_LENGTH],
        ]);

        $userId = (int) $request->user()->getAuthIdentifier();
        $this->workshop->advance($userId);
        $done = $this->slots->swap($userId, $car, (int) $data['with'], $data['request_id']);

        return $this->respond($userId, 200, ['repeated' => $done['repeated'], 'fee_cents' => $done['fee_cents']]);
    }

    /**
     * Sells a car of the driver: they are paid a share of what it was worth when they got it, and at least the scrap. What it earned until now is
     * paid first, the parts go back to the inventory, and the car is gone from the garage. The last car is not sold, and neither is one that is
     * on the road or has work waiting in the workshop.
     */
    public function sell(Request $request, int $car): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $this->workshop->advance($userId);
        $cents = $this->sales->sell($userId, $car);

        return $this->respond($userId, 200, ['sold' => ['car' => $car, 'cents' => $cents]]);
    }

    private function bringUpToDate(int $userId): void
    {
        $this->workshop->advance($userId);

        foreach (RaceRun::query()->where('user_id', $userId)->orderBy('id')->pluck('id') as $runId) {
            $this->runs->settle((int) $runId);
        }
    }

    /** @param  array<string, mixed>  $extra  more keys of the top level of the answer */
    private function respond(int $userId, int $status = 200, array $extra = []): JsonResponse
    {
        $selected = $this->garage->carOf($userId);
        $level = $this->rules->playerLevelAt($this->ledger->xp($userId));
        $summary = $this->garages->summary($userId);
        $nowMs = RunService::nowMs();

        $garages = [];

        foreach ($this->rules->regions() as $slug => $region) {
            $garages[] = [
                'city' => $slug,
                'name' => I18nText::from($region['name'])->for(),
                'level_required' => $this->rules->cityMinPlayerLevel($slug),
                'open' => $level >= $this->rules->cityMinPlayerLevel($slug),
                ...$summary[$slug],
            ];
        }

        $yard = $this->garages->yard();
        // Put in order first: the numbers of the cars are what the cars below show.
        $slots = $this->slots->state($userId);

        return response()->json([
            'data' => [
                'selected' => $selected?->id,
                'slots' => $slots,
                'cars' => $this->carsData($userId, $selected),
                'garages' => $garages,
                'yard' => $yard,
            ],
            'wallet' => ['cash_cents' => $this->ledger->balance($userId), 'xp' => $this->ledger->xp($userId)],
            'now_ms' => $nowMs,
            ...$extra,
        ], $status)->header('Cache-Control', 'no-store');
    }

    /** @return list<array<string, mixed>> */
    private function carsData(int $userId, ?Car $selected): array
    {
        $cars = $this->garage->cars($userId);

        return $cars->map(fn (Car $car): array => $this->carData($car, $selected, $cars->count()))->values()->all();
    }

    /** @return array<string, mixed> */
    private function carData(Car $car, ?Car $selected, int $carsOfDriver): array
    {
        $vehicle = $this->catalog->vehicle($car->vehicle_slug);
        $run = $this->garage->runOf($car);
        $city = $run === null ? $this->rules->homeRegion() : $this->garage->cityOf($run);
        $journey = $run === null ? null : $this->travel->activeOf($run->id);
        $gig = $run === null ? null : $this->gigs->activeOf($run->id);

        return [
            'id' => $car->id,
            'level' => $car->level,
            // How strong the car is: its PI and the class the PI is in (the rating of the competition, not the kind of body).
            'performance' => $this->performance->ratingOf($car->vehicle_slug, $car->mountedParts()),
            'selected' => $selected?->id === $car->id,
            // The place of the car among the cars that drive, or null for a car in storage (or when the game has no limit).
            'slot_no' => $car->slot_no,
            'stored' => $run?->stop_reason === RaceRun::STOP_STORED,
            'condition_percent' => (int) round($car->conditionPpm() / 10_000),
            'mileage_km' => intdiv((int) $car->mileage_m, 1000),
            // What a sale pays, and why there is none now (`last_car`, `busy`) or null.
            'sale_cents' => $this->sales->quoteCents($car),
            'sell_blocked' => $this->sales->blockedReason($car, $carsOfDriver),
            'vehicle' => [
                'slug' => $car->vehicle_slug,
                'name' => $vehicle !== null ? I18nText::from($vehicle['name'])->for() : $car->vehicle_slug,
                'image_url' => $vehicle !== null ? ContentImages::urlOf($vehicle) : null,
                'class' => $vehicle['class'] ?? null,
                'tier' => $vehicle['tier'] ?? null,
            ],
            'city' => ['slug' => $city, 'name' => $this->rules->cityName($city)],
            'run' => $run === null ? null : [
                'id' => $run->id,
                'status' => $run->status,
                'stop_reason' => $run->stop_reason,
                'laps_done' => $run->laps_done,
                'fuel_ml' => $run->fuel_ml,
                'tank_ml' => $this->inputs->tankMl($car->vehicle_slug, $car->mountedSlugs()),
                'track' => I18nText::from($this->rules->track($run->track_slug)['name'])->for(),
            ],
            'travel' => $journey === null ? null : [
                'to' => $journey->to_region,
                'to_name' => $this->rules->cityName($journey->to_region),
                'arrive_ms' => $journey->arrive_ms,
            ],
            // The gig the car is out on, when it is: it is back at `finish_ms` and is paid then.
            'gig' => $gig === null ? null : [
                'id' => $gig->id,
                'kind' => $gig->kind,
                'to_name' => $gig->to_region === null ? $this->rules->cityName($gig->region) : $this->rules->cityName($gig->to_region),
                'finish_ms' => $gig->finish_ms,
                'reward_cents' => $gig->reward_cents,
            ],
        ];
    }
}
