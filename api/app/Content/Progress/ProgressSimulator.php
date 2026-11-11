<?php

declare(strict_types=1);

namespace App\Content\Progress;

use App\Content\Catalog;
use App\Content\GameMissions;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Content\TutorialSteps;
use App\Domain\Run\CarStats;
use App\Domain\Run\RunFreezer;
use InvalidArgumentException;
use Throwable;

/**
 * Plays one driver of the game from a new account (D13 of the design of the AI bridge v2): the tutorial with its rewards, laps on the best open track (or the one asked
 * for), fuel, the workshop, purchases when the level and the cash allow, and journeys to the cities where the driver has a garage. It answers at which minute each level
 * is reached and with what (from the tutorial, the laps, the missions), what was bought and when, and where the driver drove.
 *
 * The numbers of a lap are the game's own: a car on a track is frozen as a run is (`RunInputsFactory` and `RunFreezer`), so a change to a car, a part or a rule shows here
 * as it does in the game. The driver is ideal: it makes no mistakes, it decides at the end of every lap while it is at the game, and nothing is random (the weather is
 * dry, there are no breakdowns). What it leaves out is listed in the answer, so nobody takes it for more than it is.
 */
final class ProgressSimulator
{
    private const MINUTE_MS = 60_000;

    private const HOUR_MS = 3_600_000;

    private const DAY_MS = 86_400_000;

    /** The waking day over which the visits of A, B and C are spread, and how long each of them is. */
    private const WAKING_MS = 16 * self::HOUR_MS;

    private const PLAYER_VISIT_MS = 10 * self::MINUTE_MS;

    /** A city has to be this much better than the one the driver is in to be worth the journey. */
    private const TRAVEL_GAIN = 1.05;

    /** The most purchases one decision makes, so a long list never stalls a lap. */
    private const PURCHASES_AT_ONCE = 5;

    private ProgressPlan $plan;

    private int $now = 0;

    private int $cash = 0;

    /** @var array{tutorial: int, laps: int, missions: int} */
    private array $xp = ['tutorial' => 0, 'laps' => 0, 'missions' => 0];

    private int $level = 1;

    private string $car;

    private int $carXp = 0;

    /** @var array<string, string> the parts on the car, by slot */
    private array $mounted = [];

    /** @var list<string> the cars the driver has */
    private array $cars = [];

    private string $region;

    /** @var list<string> the cities where the driver has a garage */
    private array $garages = [];

    private ?string $track = null;

    private int $fuelMl = 0;

    private int $busyUntil = 0;

    private int $laps = 0;

    private int $drivingMs = 0;

    private int $listAt = 0;

    private bool $missionsOpen = false;

    /** A part the tutorial gave that waits for its step mount. */
    private ?string $gift = null;

    /** @var list<array<string, mixed>> */
    private array $levels = [];

    /** @var list<array<string, mixed>> */
    private array $purchases = [];

    /** @var list<array<string, mixed>> */
    private array $moves = [];

    /** @var array<string, array{code: string, slug: string|null, text: string}> what happened that the reader should know, once each */
    private array $notes = [];

    /** The visits a day of a player of the economy simulator (A, B, C). */
    private int $visits = 0;

    /** @var array<string, array{lap_ms: int, cash: int, xp: int, fuel_ml: int, tank_ml: int}|null> the numbers of a lap and the tank, by car, parts and track */
    private array $lapCache = [];

    /** @var array<string, array<string, float>> what a purchase adds to an hour, by the car with its parts and by the purchase */
    private array $gainCache = [];

    /** @var array<string, CarStats> */
    private array $statsCache = [];

    public function __construct(
        private readonly Rules $rules,
        private readonly Catalog $catalog,
        private readonly RunInputsFactory $factory,
        private readonly TutorialSteps $tutorial,
        private readonly GameMissions $missions,
    ) {}

    /**
     * @param  array{visits_per_day: int}|null  $player  the player of the economy simulator, for the ways of playing A, B and C
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the plan names a car or a track the game does not have
     */
    public function simulate(ProgressPlan $plan, ?array $player = null): array
    {
        $this->reset($plan);

        if (in_array($plan->style, ['A', 'B', 'C'], true) && ($player['visits_per_day'] ?? 0) < 1) {
            throw new InvalidArgumentException("The player {$plan->style} of the economy simulator has no visits a day.");
        }

        $visits = (int) ($player['visits_per_day'] ?? 0);
        $this->visits = $visits;
        $end = $plan->minutes * self::MINUTE_MS;
        $tutorial = $plan->tutorial ? $this->tutorialSchedule($plan, $visits) : [];
        $nextDay = self::DAY_MS;
        $this->missionsOpen = ! $plan->tutorial;

        $this->recordLevel();

        while ($this->now < $end) {
            $present = $this->present($this->now, $visits);

            if ($present) {
                $this->decide();
            }

            $lap = $this->track === null ? null : $this->lap($this->car, $this->mounted, $this->track);
            $drives = $lap !== null && $this->now >= $this->busyUntil && $this->fuelMl >= $lap['fuel_ml'];

            $until = $drives ? $this->now + $lap['lap_ms'] : $this->nextMoment($end, $visits, $tutorial, $nextDay);

            // What falls before the end of the lap (or of the wait) happens at its time: a reward of the tutorial, the missions of a day.
            while ($tutorial !== [] && $tutorial[0]['at'] <= $until && $tutorial[0]['at'] <= $end) {
                $this->tutorialStep(array_shift($tutorial));
            }

            if ($this->plan->missions && $nextDay <= $until && $nextDay <= $end) {
                $this->missionsOfADay();
                $nextDay += self::DAY_MS;
            }

            if ($until > $end) {
                $this->now = $end;

                break;
            }

            if ($drives) {
                $this->cash += $lap['cash'];
                $this->xp['laps'] += $lap['xp'];
                $this->carXp += $this->rules->carXpPerLap();
                $this->fuelMl -= $lap['fuel_ml'];
                $this->laps++;
                $this->drivingMs += $lap['lap_ms'];
            }

            $this->now = max($until, $this->now + 1);
            $this->recordLevel();
        }

        return $this->answer($end);
    }

    private function reset(ProgressPlan $plan): void
    {
        $this->plan = $plan;
        $this->now = 0;
        $this->cash = 0;
        $this->xp = ['tutorial' => 0, 'laps' => 0, 'missions' => 0];
        $this->level = 1;
        $this->car = $plan->car ?? $this->startingCar();

        if ($this->catalog->vehicle($this->car) === null) {
            throw new InvalidArgumentException("There is no car {$this->car}.");
        }

        if ($plan->track !== null && $this->rules->track($plan->track) === null) {
            throw new InvalidArgumentException("There is no track {$plan->track}.");
        }

        $this->carXp = $this->rules->carXpToReach($this->rules->carStartLevel());
        $this->mounted = [];
        $this->cars = [$this->car];
        $this->region = $this->rules->homeRegion();
        $this->garages = [$this->region];
        $this->track = null;
        $tank = $this->factory->tankMl($this->car);
        $this->fuelMl = $plan->tutorial ? min($tank, (int) ($this->tutorial->script()['first_run_fuel_ml'] ?? $tank)) : $tank;
        $this->busyUntil = 0;
        $this->laps = 0;
        $this->drivingMs = 0;
        $this->listAt = 0;
        $this->gift = null;
        $this->levels = [];
        $this->purchases = [];
        $this->moves = [];
        $this->notes = [];
        $this->gainCache = [];
    }

    /** The cheapest car a new driver may start with. */
    private function startingCar(): string
    {
        $cars = $this->catalog->startingVehicles();

        if ($cars === []) {
            throw new InvalidArgumentException('The game has no car to start with.');
        }

        uasort($cars, static fn (array $a, array $b): int => [$a['price_cents'], $a['slug']] <=> [$b['price_cents'], $b['slug']]);

        return (string) array_key_first($cars);
    }

    /** What the driver does at the game: the track and the city, the purchases, the fuel. */
    private function decide(): void
    {
        if ($this->now < $this->busyUntil) {
            return;
        }

        $this->chooseTrack();
        $this->buy();
        $this->chooseTrack();
        $this->refuel();
    }

    private function chooseTrack(): void
    {
        $wanted = $this->plan->track;

        if ($wanted !== null) {
            $region = (string) $this->rules->track($wanted)['region'];

            if ($this->rules->trackMinPlayerLevel($wanted) <= $this->level) {
                if ($region === $this->region) {
                    $this->driveOn($wanted, 'asked');

                    return;
                }

                if ($this->plan->travel && in_array($region, $this->garages, true) && $this->travelTo($region)) {
                    return;
                }
            }
        }

        [$best, $value] = $this->bestTrackIn($this->region, $this->car, $this->mounted);

        if ($this->plan->travel && $wanted === null) {
            foreach ($this->garages as $region) {
                if ($region === $this->region || $this->rules->cityMinPlayerLevel($region) > $this->level) {
                    continue;
                }

                [$there, $valueThere] = $this->bestTrackIn($region, $this->car, $this->mounted);

                if ($there !== null && $valueThere > max($value, 0.0) * self::TRAVEL_GAIN && $this->journeyPaysOff($region, $value, $valueThere) && $this->travelTo($region)) {
                    return;
                }
            }
        }

        if ($best !== null) {
            $this->driveOn($best, $wanted === null ? 'best' : 'asked_not_open');
        }
    }

    private function driveOn(string $track, string $why): void
    {
        if ($this->track === $track) {
            return;
        }

        $this->moves[] = ['minute' => $this->minute(), 'kind' => 'track', 'from' => $this->track, 'to' => $track, 'why' => $why, 'level' => $this->level];
        $this->track = $track;
    }

    /**
     * The best open track of a city for a car, by the goal of the plan, and how good it is (profit or experience of an hour).
     *
     * @param  array<string, string>  $mounted
     * @return array{0: string|null, 1: float}
     */
    private function bestTrackIn(string $region, string $car, array $mounted): array
    {
        $best = null;
        $value = -INF;

        foreach ($this->rules->tracksOf($region) as $track) {
            if ($this->rules->trackMinPlayerLevel($track) > $this->level) {
                continue;
            }

            $lap = $this->lap($car, $mounted, $track);

            if ($lap === null) {
                continue;
            }

            $worth = $this->worth($lap);

            if ($worth > $value) {
                [$best, $value] = [$track, $worth];
            }
        }

        return [$best, $best === null ? 0.0 : $value];
    }

    /**
     * What a track (or a car with its parts on it) is worth to this driver, by the goal of the plan: an hour of driving for a driver who never leaves; for one who comes
     * back now and then, what a visit yields, which is a tank or the time to the next visit, whichever ends first (a thirsty car drives less while nobody refuels it).
     *
     * @param  array{lap_ms: int, cash: int, xp: int, fuel_ml: int, tank_ml: int}  $lap
     */
    private function worth(array $lap): float
    {
        $ofALap = $this->plan->goal === 'xp' ? (float) $lap['xp'] : $lap['cash'] - $lap['fuel_ml'] / 1000 * $this->rules->fuelPriceCentsPerLiter();

        if ($this->plan->style === 'active') {
            return $ofALap * self::HOUR_MS / $lap['lap_ms'];
        }

        $gap = $this->plan->style === 'every' ? $this->plan->everyMinutes * self::MINUTE_MS : intdiv(self::WAKING_MS, max(1, $this->visits));

        return $ofALap * min(intdiv($lap['tank_ml'], $lap['fuel_ml']), $gap / $lap['lap_ms']);
    }

    /**
     * Whether a journey is worth it in the time the plan has left: what the better city adds over that time has to be more than what the hours on the road lose.
     */
    private function journeyPaysOff(string $region, float $here, float $there): bool
    {
        $km = $this->kmTo($region);

        if ($km === null) {
            return false;
        }

        $speedKmh = $this->stats()->get('top_speed') * $this->rules->travelSpeedSharePpm() / 1_000_000;
        $journeyHours = $km / max(1.0, $speedKmh);
        $hoursLeft = ($this->plan->minutes * self::MINUTE_MS - $this->now) / self::HOUR_MS - $journeyHours;

        return ($there - $here) * $hoursLeft > max($here, 0.0) * $journeyHours;
    }

    /** A journey to a city where the driver has a garage, by the shortest road; false when the fuel for it cannot be had. */
    private function travelTo(string $region): bool
    {
        $km = $this->kmTo($region);

        if ($km === null) {
            return false;
        }

        $stats = $this->stats();
        $speedKmh = $stats->get('top_speed') * $this->rules->travelSpeedSharePpm() / 1_000_000;
        $ms = max(1, (int) round($km / $speedKmh * self::HOUR_MS));
        $fuel = max(1, (int) ceil($km * $this->rules->travelFuelMlPerKm() * $stats->fuelUseMultiplier));

        if ($this->fuelMl < $fuel) {
            $this->refuel($fuel);
        }

        if ($this->fuelMl < $fuel) {
            return false;
        }

        $this->fuelMl -= $fuel;
        $this->busyUntil = $this->now + $ms;
        $this->moves[] = ['minute' => $this->minute(), 'kind' => 'journey', 'from' => $this->region, 'to' => $region, 'km' => $km, 'journey_minutes' => round($ms / self::MINUTE_MS, 1), 'level' => $this->level];
        $this->region = $region;
        $this->track = null;
        [$best] = $this->bestTrackIn($region, $this->car, $this->mounted);

        if ($best !== null) {
            $this->driveOn($best, 'arrived');
        }

        return true;
    }

    /** The kilometres of the shortest way by road from the city the driver is in. */
    private function kmTo(string $region): ?int
    {
        $distance = [$this->region => 0];
        $open = [$this->region];

        while ($open !== []) {
            usort($open, static fn (string $a, string $b): int => $distance[$a] <=> $distance[$b]);
            $here = array_shift($open);

            foreach ($this->rules->roads() as $road) {
                $next = $road['from'] === $here ? $road['to'] : ($road['to'] === $here ? $road['from'] : null);

                if ($next !== null && (! isset($distance[$next]) || $distance[$next] > $distance[$here] + $road['km'])) {
                    $distance[$next] = $distance[$here] + $road['km'];
                    $open[] = $next;
                }
            }
        }

        return $distance[$region] ?? null;
    }

    /** Fills the tank (or puts in at least `$needMl`), as far as the cash goes; a driver with no cash for a lap is towed, as the game does. */
    private function refuel(int $needMl = 0): void
    {
        $lap = $this->track === null ? null : $this->lap($this->car, $this->mounted, $this->track);
        $need = max($needMl, $lap['fuel_ml'] ?? 0);

        if ($this->fuelMl >= $need && $needMl === 0) {
            return;
        }

        $tank = $this->factory->tankMl($this->car, $this->mounted);
        $price = $this->rules->fuelPriceCentsPerLiter();
        $add = max(0, $tank - $this->fuelMl);
        $affordable = (int) floor($this->cash * 1000 / max(1, $price));
        $add = min($add, $affordable);

        if ($add > 0) {
            $this->cash -= (int) ceil($add * $price / 1000);
            $this->fuelMl += $add;
        }

        if ($this->fuelMl < $need && $lap !== null && $needMl === 0) {
            $this->fuelMl += $lap['fuel_ml'] * $this->rules->towFuelLaps();
            $this->busyUntil = max($this->busyUntil, $this->now + $this->rules->towDelayMs());
            $this->note('towed', null, 'The driver had no cash for the fuel of a lap and was towed (free fuel for '.$this->rules->towFuelLaps().' laps after a wait), as the game does.');
        }
    }

    /** What the way of buying of the plan buys now, keeping the cash of a full tank. */
    private function buy(): void
    {
        for ($bought = 0; $bought < self::PURCHASES_AT_ONCE; $bought++) {
            $next = match ($this->plan->buy) {
                'list' => $this->nextOnTheList(),
                'best' => $this->bestBuy(),
                default => null,
            };

            if ($next === null) {
                return;
            }

            $this->purchase($next['kind'], $next['slug'], $next['price']);
        }
    }

    /** @return array{kind: string, slug: string, price: int}|null */
    private function nextOnTheList(): ?array
    {
        while ($this->listAt < count($this->plan->list)) {
            $slug = $this->plan->list[$this->listAt];
            $part = $this->catalog->part($slug);
            $vehicle = $part === null ? $this->catalog->vehicle($slug) : null;

            if ($part === null && $vehicle === null) {
                $this->note('list_unknown', $slug, "{$slug} is neither a part nor a car of the game: skipped.");
                $this->listAt++;

                continue;
            }

            if ($vehicle !== null && ! $this->forSale($vehicle)) {
                $this->note('list_not_sold', $slug, "{$slug} is not sold at the dealer: skipped.");
                $this->listAt++;

                continue;
            }

            if ($part !== null && ! in_array($slug, $this->rules->shopParts(), true)) {
                $this->note('list_not_sold', $slug, "{$slug} is not sold in the shop: skipped.");
                $this->listAt++;

                continue;
            }

            $kind = $part !== null ? 'part' : 'car';
            $price = (int) ($part ?? $vehicle)['price_cents'];
            $ready = $kind === 'part'
                ? $this->carLevel() >= $this->rules->requiredCarLevel((int) $part['tier']) && $this->lap($this->car, [...$this->mounted, (string) $part['slot'] => $slug], $this->track ?? $this->rules->startingTrack()) !== null
                : $this->level >= (int) $vehicle['min_player_level'];

            if ($ready && $this->cash - $price >= $this->reserve()) {
                $this->listAt++;

                return ['kind' => $kind, 'slug' => $slug, 'price' => $price];
            }

            return null;
        }

        return null;
    }

    /**
     * The purchase that adds the most to an hour of the goal for a dollar, among the parts the car can have now and the cars the dealer sells to this level, when the
     * cash allows it.
     *
     * @return array{kind: string, slug: string, price: int}|null
     */
    private function bestBuy(): ?array
    {
        $budget = $this->cash - $this->reserve();

        // Weighing a part or a car takes the time of freezing a run, so only what the cash can pay for now is weighed, and each of them once for a car with its parts.
        if ($budget < $this->cheapest()) {
            return null;
        }

        ksort($this->mounted);
        $key = $this->car.'|'.json_encode($this->mounted).'|'.$this->region.'|'.$this->level.'|'.$this->track;
        [$best, $now] = $this->bestTrackIn($this->region, $this->car, $this->mounted);
        $track = $this->track ?? $best;
        $here = $track === null ? null : $this->lap($this->car, $this->mounted, $track);
        $now = $here === null ? $now : $this->worth($here);
        $choice = null;

        foreach ($this->rules->shopParts() as $slug) {
            $part = $this->catalog->part($slug);

            if ($part === null || (int) $part['price_cents'] > $budget || ($this->mounted[$part['slot']] ?? null) === $slug || $this->carLevel() < $this->rules->requiredCarLevel((int) $part['tier'])) {
                continue;
            }

            // A part is weighed on the track the driver is on (the best track is chosen again after a purchase): weighing it on every track would cost the time of
            // the whole simulation.
            $gain = $this->gainCache[$key]['part:'.$slug] ??= (function () use ($part, $slug, $track, $now): float {
                $with = $track === null ? null : $this->lap($this->car, [...$this->mounted, (string) $part['slot'] => $slug], $track);

                return $with === null ? 0.0 : $this->worth($with) - $now;
            })();
            $choice = $this->better($choice, 'part', $slug, (int) $part['price_cents'], $gain);
        }

        foreach ($this->catalog->vehicles() as $slug => $vehicle) {
            if ((int) $vehicle['price_cents'] > $budget || in_array($slug, $this->cars, true) || ! $this->forSale($vehicle) || $this->level < (int) $vehicle['min_player_level']) {
                continue;
            }

            $gain = $this->gainCache[$key]['car:'.$slug] ??= $this->bestTrackIn($this->region, (string) $slug, [])[1] - $now;
            $choice = $this->better($choice, 'car', (string) $slug, (int) $vehicle['price_cents'], $gain);
        }

        return $choice === null ? null : ['kind' => $choice['kind'], 'slug' => $choice['slug'], 'price' => $choice['price']];
    }

    /**
     * The better of two purchases: the one that adds more to an hour for a dollar (by its slug when they are even); one that adds nothing is never chosen.
     *
     * @param  array{kind: string, slug: string, price: int, ratio: float}|null  $choice
     * @return array{kind: string, slug: string, price: int, ratio: float}|null
     */
    private function better(?array $choice, string $kind, string $slug, int $price, float $gain): ?array
    {
        if ($gain <= 0 || $price <= 0) {
            return $choice;
        }

        $ratio = $gain / $price;

        return $choice === null || $ratio > $choice['ratio'] || ($ratio === $choice['ratio'] && $slug < $choice['slug'])
            ? ['kind' => $kind, 'slug' => $slug, 'price' => $price, 'ratio' => $ratio]
            : $choice;
    }

    /** The price of the cheapest part the car can have now or car the dealer sells to this level, in cents. */
    private function cheapest(): int
    {
        $prices = [];

        foreach ($this->rules->shopParts() as $slug) {
            $part = $this->catalog->part($slug);

            if ($part !== null && $this->carLevel() >= $this->rules->requiredCarLevel((int) $part['tier'])) {
                $prices[] = (int) $part['price_cents'];
            }
        }

        foreach ($this->catalog->vehicles() as $vehicle) {
            if ($this->forSale($vehicle) && $this->level >= (int) $vehicle['min_player_level']) {
                $prices[] = (int) $vehicle['price_cents'];
            }
        }

        return $prices === [] ? PHP_INT_MAX : min($prices);
    }

    /** @param  array<string, mixed>  $vehicle */
    private function forSale(array $vehicle): bool
    {
        return in_array('dealer', (array) $vehicle['sources'], true) && ($vehicle['powertrain'] ?? 'ice') === 'ice';
    }

    private function purchase(string $kind, string $slug, int $price): void
    {
        $this->cash -= $price;

        if ($kind === 'part') {
            $part = (array) $this->catalog->part($slug);
            $this->mount((string) $part['slot'], $slug);
        } else {
            $this->cars[] = $slug;
            $this->car = $slug;
            $this->mounted = [];
            $this->carXp = $this->rules->carXpToReach($this->rules->carStartLevel());
            $this->fuelMl = 0;
            $this->track = null;
        }

        $this->purchases[] = ['minute' => $this->minute(), 'kind' => $kind, 'slug' => $slug, 'price_usd' => $price / 100, 'level' => $this->level, 'cash_after_usd' => $this->cash / 100];
        $this->chooseTrack();
    }

    /** Puts a part on the car: the car stands in the workshop for the time of the job (and of taking the old part off). */
    private function mount(string $slot, string $slug, ?int $ms = null): void
    {
        $part = (array) $this->catalog->part($slug);
        $old = $this->mounted[$slot] ?? null;
        $time = $ms ?? $this->rules->workshopJobMs($slot, (int) $part['tier']);

        if ($old !== null && $ms === null) {
            $time += $this->rules->workshopJobMs($slot, (int) ($this->catalog->part($old)['tier'] ?? 1), removal: true);
        }

        $this->mounted[$slot] = $slug;
        $this->busyUntil = max($this->busyUntil, $this->now) + $time;
    }

    /** The cash an ideal driver keeps: a full tank, so that a purchase never leaves the car standing. */
    private function reserve(): int
    {
        return (int) ceil($this->factory->tankMl($this->car, $this->mounted) * $this->rules->fuelPriceCentsPerLiter() / 1000);
    }

    /**
     * The steps of the tutorial that reward (phases 1 and 2, as the economy simulator counts them), each done after `step_seconds` more of the driver's time at the game.
     *
     * @return list<array{at: int, step: array<string, mixed>}>
     */
    private function tutorialSchedule(ProgressPlan $plan, int $visits): array
    {
        $steps = array_values(array_filter($this->tutorial->all(), static fn (array $step): bool => (int) $step['phase'] <= 2));
        $schedule = [];

        foreach ($steps as $index => $step) {
            $schedule[] = ['at' => $this->wallTimeOf(($index + 1) * $plan->stepSeconds * 1000, $visits), 'step' => $step];
        }

        return $schedule;
    }

    /** @param  array{at: int, step: array<string, mixed>}  $entry */
    private function tutorialStep(array $entry): void
    {
        $step = $entry['step'];
        $reward = (array) ($step['reward'] ?? []);
        $this->now = max($this->now, $entry['at']);

        $this->xp['tutorial'] += (int) ($reward['xp'] ?? 0);
        $this->carXp += (int) ($reward['car_xp'] ?? 0);

        // The repair of the tutorial gives back what the breakdown of the tutorial cost, so the cash of that step is not a gift.
        if ($step['key'] !== 'repair') {
            $this->cash += (int) ($reward['cash_cents'] ?? 0);
        }

        if (isset($reward['garage']) && ! in_array($reward['garage'], $this->garages, true)) {
            $this->garages[] = (string) $reward['garage'];
        }

        if (isset($reward['part']) && $this->catalog->part((string) $reward['part']) !== null) {
            $this->note('gift', (string) $reward['part'], "The tutorial gives {$reward['part']}; the driver mounts it at the step mount.");
            $this->gift = (string) $reward['part'];
        }

        if ($step['key'] === 'mount' && $this->gift !== null) {
            $part = (array) $this->catalog->part($this->gift);
            $this->mount((string) $part['slot'], $this->gift, (int) ($this->tutorial->script()['first_mount_ms'] ?? 30_000));
            $this->gift = null;
        }

        if ($step['key'] === 'travel') {
            $this->missionsOpen = true;
            $garage = $this->garages[1] ?? null;

            $this->recordLevel();

            if ($this->plan->travel && $garage !== null && $this->rules->cityMinPlayerLevel($garage) <= $this->level) {
                $this->travelTo($garage);
            }
        }

        $this->recordLevel();
    }

    /** What the missions of a day add, at its end: the share of a day of the sets the game has, in tanks of the car on its track (`MissionEconomy`). */
    private function missionsOfADay(): void
    {
        $economy = $this->missions->economy();
        $lap = $this->track === null ? null : $this->lap($this->car, $this->mounted, $this->track);

        if ($economy === null || $lap === null || ! $this->missionsOpen) {
            return;
        }

        $tanks = $economy->tanksPerDay($this->level);
        $lapsInATank = intdiv($this->factory->tankMl($this->car, $this->mounted), max(1, $lap['fuel_ml']));
        $netOfALap = $lap['cash'] - $lap['fuel_ml'] / 1000 * $this->rules->fuelPriceCentsPerLiter();

        $this->cash += (int) round($tanks['cash'] * $lapsInATank * $netOfALap);
        $this->xp['missions'] += (int) round($tanks['xp'] * $lapsInATank * $lap['xp']);
        $this->recordLevel();
    }

    /** The next moment something can change for a car that does not drive: the end of a job or a journey, the next visit, a reward, a day, the end. */
    private function nextMoment(int $end, int $visits, array $tutorial, int $nextDay): int
    {
        $moments = [$end];

        if ($this->busyUntil > $this->now) {
            $moments[] = $this->busyUntil;
        }

        // The next visit that starts after this moment: within a visit the driver is already here, and what it does next waits for the car or for a reward.
        $visit = $this->nextVisit($this->visitEnd($this->now, $visits) + 1, $visits);

        if ($visit !== null) {
            $moments[] = $visit;
        }

        if ($tutorial !== []) {
            $moments[] = $tutorial[0]['at'];
        }

        if ($this->plan->missions) {
            $moments[] = $nextDay;
        }

        return max($this->now + 1, min($moments));
    }

    /** Whether the driver is at the game at a moment. */
    private function present(int $at, int $visits): bool
    {
        return match ($this->plan->style) {
            'active' => true,
            'every' => $at % ($this->plan->everyMinutes * self::MINUTE_MS) < $this->plan->visitMinutes * self::MINUTE_MS,
            default => $this->inPlayerVisit($at, $visits),
        };
    }

    private function inPlayerVisit(int $at, int $visits): bool
    {
        $inDay = $at % self::DAY_MS;
        $gap = intdiv(self::WAKING_MS, $visits);
        $visit = intdiv($inDay, $gap);

        return $visit < $visits && $inDay - $visit * $gap < self::PLAYER_VISIT_MS;
    }

    /** The start of the first visit at or after a moment (the moment itself when the driver is at the game then); null for a driver who never leaves. */
    private function nextVisit(int $from, int $visits): ?int
    {
        if ($this->plan->style === 'active') {
            return null;
        }

        if ($this->plan->style === 'every') {
            $every = $this->plan->everyMinutes * self::MINUTE_MS;

            return $from % $every < $this->plan->visitMinutes * self::MINUTE_MS ? $from : (intdiv($from, $every) + 1) * $every;
        }

        $gap = intdiv(self::WAKING_MS, $visits);
        $day = intdiv($from, self::DAY_MS) * self::DAY_MS;

        for ($index = 0; $index <= $visits; $index++) {
            $start = $index < $visits ? $day + $index * $gap : $day + self::DAY_MS;

            if ($from < $start + ($index < $visits ? self::PLAYER_VISIT_MS : 1)) {
                return max($from, $start);
            }
        }

        return $day + self::DAY_MS;
    }

    /** The end of the visit a moment falls in, or the moment itself when the driver is not at the game then (or never leaves it). */
    private function visitEnd(int $at, int $visits): int
    {
        if ($this->plan->style === 'active' || ! $this->present($at, $visits)) {
            return $at;
        }

        if ($this->plan->style === 'every') {
            $every = $this->plan->everyMinutes * self::MINUTE_MS;

            return intdiv($at, $every) * $every + $this->plan->visitMinutes * self::MINUTE_MS;
        }

        $gap = intdiv(self::WAKING_MS, $visits);
        $inDay = $at % self::DAY_MS;

        return $at - $inDay + intdiv($inDay, $gap) * $gap + self::PLAYER_VISIT_MS;
    }

    /** The moment the driver has spent this much time at the game, for the steps of the tutorial (they are done only while the driver is there). */
    private function wallTimeOf(int $presenceMs, int $visits): int
    {
        if ($this->plan->style === 'active') {
            return $presenceMs;
        }

        $visit = $this->plan->style === 'every' ? $this->plan->visitMinutes * self::MINUTE_MS : self::PLAYER_VISIT_MS;
        $at = 0;
        $left = $presenceMs;

        while (true) {
            $start = $this->nextVisit($at, $visits);

            if ($left <= $visit) {
                return $start + $left;
            }

            $left -= $visit;
            $at = $start + $visit;
        }
    }

    /**
     * The numbers of a lap of a car with these parts on a track, frozen as the game freezes a run (dry weather); null when the car cannot race there with them.
     *
     * @param  array<string, string>  $mounted
     * @return array{lap_ms: int, cash: int, xp: int, fuel_ml: int, tank_ml: int}|null
     */
    private function lap(string $car, array $mounted, string $track): ?array
    {
        ksort($mounted);
        $key = $car.'|'.json_encode($mounted).'|'.$track;

        if (! array_key_exists($key, $this->lapCache)) {
            try {
                $config = (new RunFreezer)->freeze($this->factory->make($car, $track, $mounted), 'progress', 0)->config;
                $this->lapCache[$key] = [
                    'lap_ms' => max(1, (int) $config['lap_ms']['dry']),
                    'cash' => (int) $config['lap_cash_cents']['dry'],
                    'xp' => (int) $config['lap_xp']['dry'],
                    'fuel_ml' => max(1, (int) $config['fuel_per_lap_ml']),
                    'tank_ml' => $this->factory->tankMl($car, $mounted),
                ];
            } catch (Throwable) {
                $this->lapCache[$key] = null;
            }
        }

        return $this->lapCache[$key];
    }

    /** The stats of the car with its parts, made once for each set of parts. */
    private function stats(): CarStats
    {
        ksort($this->mounted);
        $key = $this->car.'|'.json_encode($this->mounted);

        return $this->statsCache[$key] ??= $this->factory->stats($this->car, $this->mounted);
    }

    private function carLevel(): int
    {
        return $this->rules->carLevelAt($this->carXp);
    }

    private function totalXp(): int
    {
        return array_sum($this->xp);
    }

    private function minute(): float
    {
        return round($this->now / self::MINUTE_MS, 1);
    }

    /** Writes down something the reader should know, once. */
    private function note(string $code, ?string $slug, string $text): void
    {
        $this->notes[$code.'|'.$slug] = ['code' => $code, 'slug' => $slug, 'text' => $text];
    }

    /** Writes down every level the experience has reached since the last time. */
    private function recordLevel(): void
    {
        $reached = $this->rules->playerLevelAt($this->totalXp());

        if ($this->levels === []) {
            $this->levels[] = $this->levelRow(1);
        }

        while ($this->level < $reached) {
            $this->level++;
            $this->levels[] = $this->levelRow($this->level);
            $this->gainCache = [];
        }
    }

    /** @return array<string, mixed> */
    private function levelRow(int $level): array
    {
        return [
            'level' => $level,
            'minute' => $this->minute(),
            'xp' => $this->totalXp(),
            'xp_tutorial' => $this->xp['tutorial'],
            'xp_laps' => $this->xp['laps'],
            'xp_missions' => $this->xp['missions'],
            'cash_usd' => $this->cash / 100,
            'car' => $this->car,
            'car_level' => $this->carLevel(),
            'parts' => array_values($this->mounted),
            'track' => $this->track,
        ];
    }

    /** @return array<string, mixed> */
    private function answer(int $end): array
    {
        return [
            'plan' => $this->plan->toArray(),
            'summary' => [
                'minutes' => $this->plan->minutes,
                'level' => $this->level,
                'xp' => $this->totalXp(),
                'xp_tutorial' => $this->xp['tutorial'],
                'xp_laps' => $this->xp['laps'],
                'xp_missions' => $this->xp['missions'],
                'cash_usd' => $this->cash / 100,
                'car' => $this->car,
                'car_level' => $this->carLevel(),
                'parts' => $this->mounted,
                'city' => $this->region,
                'track' => $this->track,
                'laps' => $this->laps,
                'driving_minutes' => round($this->drivingMs / self::MINUTE_MS, 1),
                'standing_minutes' => round(max(0, $end - $this->drivingMs) / self::MINUTE_MS, 1),
            ],
            'levels' => $this->levels,
            'purchases' => $this->purchases,
            'moves' => $this->moves,
            'notes' => array_values($this->notes),
            'assumptions' => $this->assumptions(),
        ];
    }

    /** @return array<string, string> what the simulator leaves out, by code */
    private function assumptions(): array
    {
        $assumptions = [
            'ideal_player' => 'An ideal driver: no mistakes, it decides at the end of every lap while it is at the game, and keeps the cash of a full tank.',
            'dry_weather' => 'Every lap is dry: no rain, no fog, no night.',
            'no_breakdowns' => 'No breakdowns, no wear and no service.',
            'tutorial' => $this->plan->tutorial
                ? "The rewards of the tutorial of phases 1 and 2, one step every {$this->plan->stepSeconds} s of time at the game; what a crate holds is not counted, the later phases are not played."
                : 'No tutorial: the driver starts with a full tank and nothing else.',
            'no_upgrades' => 'No upgrades (+N), no crates, no merging, no exchange.',
            'garages' => 'Garages only in the home city and the ones the tutorial gives; none is bought.',
            'no_gigs' => 'No gigs, no travel boost, no paid extras.',
            'missions' => $this->plan->missions
                ? 'Missions: the daily share of the sets of the game, counted at the end of every day of play, after the step travel of the tutorial.'
                : 'No missions.',
        ];

        if (in_array($this->plan->style, ['A', 'B', 'C'], true)) {
            $assumptions['visits'] = "Player {$this->plan->style}: its visits a day spread over a waking day of 16 hours, 10 minutes each; the car drives on its own while the tank lasts.";
        }

        return $assumptions;
    }
}
