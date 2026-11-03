<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Catalog;
use App\Content\GigCatalog;
use App\Content\Rules;
use App\Content\RunInputsFactory;
use App\Content\TutorialSteps;
use App\Domain\Gigs\GigBoard;
use App\Domain\Gigs\GigOffer;
use App\Domain\Run\CarStats;
use App\Domain\Run\GameClock;
use App\Domain\Run\RoadMap;
use App\Domain\Settlement\Ppm;
use App\Exceptions\RuleRefusedException;
use App\Models\Car;
use App\Models\Gig;
use App\Models\RaceRun;
use App\Models\TutorialProgress;
use App\Models\Wallet;
use App\Support\I18nText;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The gigs of a driver: a round trip of a car with people or a load, taken from the board of the city the car stands in. Like a journey or a job of the workshop it
 * runs by itself and nothing runs in the background: the car stands (stopped for the gig, so the settlement counts no laps) until its time is over, and it is paid and
 * put back on its track the next time anything asks (`advance`). The pay, the time and the fuel are frozen when the gig is taken.
 *
 * - What a gig pays is the pay of an hour of driving at the rung of the ladder the car belongs to, a part of it (by the level of the driver), times the multipliers of the
 *   template, of the phase of the day and of the offer, times the reference time of the trip. The car decides only how long it takes and how much fuel it burns, and
 *   so whether it can take the gig at all.
 * - The gig is paid once: the state moves from active to done in the transaction that posts the reward under the key `gig:{id}`.
 *
 * Everything runs under the lock of the wallet and then the runs, like the settlement, the workshop and the journeys.
 */
final class Gigs
{
    private readonly GigBoard $board;

    public function __construct(
        private readonly RunService $runs,
        private readonly Ledger $ledger,
        private readonly Rules $rules,
        private readonly RunInputsFactory $inputs,
        private readonly Garage $garage,
        private readonly CityGarages $garages,
        private readonly GigCatalog $catalog,
        private readonly Catalog $vehicles,
        private readonly CarProgress $progress,
        private readonly TutorialSteps $steps,
        private readonly GigPolice $police,
    ) {
        $this->board = new GigBoard($catalog);
    }

    /** Finishes the gigs that are due, if there are any. Safe to call at any time; a cheap read when no car is out. */
    public function advance(int $userId, ?int $nowMs = null): void
    {
        if (! Gig::query()->where('user_id', $userId)->where('status', Gig::STATUS_ACTIVE)->exists()) {
            return;
        }

        $this->locked($userId, $nowMs, function (Collection $runs, int $now) use ($userId): void {
            $this->finishDue($userId, $runs, $now);
        });
    }

    /** The gig a run is out on, or null. */
    public function activeOf(int $runId): ?Gig
    {
        return Gig::query()->where('race_run_id', $runId)->where('status', Gig::STATUS_ACTIVE)->first();
    }

    /** Why a driver has no board yet: `disabled` (the game has no gigs) or `tutorial` (they have not got far enough); null when they have it. */
    public function lockedBy(int $userId): ?string
    {
        if (! $this->catalog->enabled()) {
            return 'disabled';
        }

        $step = $this->catalog->unlockAfterStep();

        if ($this->steps->find($step) === null) {
            return null;
        }

        $rows = TutorialProgress::query()->where('user_id', $userId)->get()->keyBy('step_key');

        if ($rows->contains('state', TutorialProgress::STATE_SKIPPED)) {
            return null;
        }

        return in_array($rows->get($step)?->state, [TutorialProgress::STATE_DONE, TutorialProgress::STATE_DECLINED], true) ? null : 'tutorial';
    }

    /**
     * The board of the city a car stands in, as it looks for that car: what each offer would pay, how long it would take and burn, and why the car cannot take it.
     *
     * @param  int|null  $carId  the car, or the one the driver plays when left out
     * @return array<string, mixed>
     */
    public function view(int $userId, ?int $carId = null, ?int $nowMs = null): array
    {
        $now = $nowMs ?? RunService::nowMs();
        $this->advance($userId, $now);

        $car = $carId === null ? $this->garage->carOf($userId) : Car::query()->where('user_id', $userId)->find($carId);

        if ($carId !== null && $car === null) {
            throw new RuleRefusedException('car_not_found');
        }

        $run = $car === null ? null : $this->garage->runOf($car);
        $locked = $this->lockedBy($userId);
        $level = $this->rules->playerLevelAt($this->ledger->xp($userId));
        $region = $run === null ? $this->rules->homeRegion() : $this->garage->cityOf($run);
        $base = [
            'enabled' => $this->catalog->enabled(),
            'locked' => $locked,
            'city' => $region,
            'city_name' => $this->rules->cityName($region),
            'now_ms' => $now,
            'phase' => GameClock::phaseAt($now),
            'next_phase_ms' => GameClock::nextPhaseChangeMs($now),
            'wave' => ['number' => intdiv($now, $this->catalog->waveMs()), 'ends_ms' => (intdiv($now, $this->catalog->waveMs()) + 1) * $this->catalog->waveMs()],
            'cancel_window_ms' => $this->catalog->cancelWindowMs(),
            'arrest' => $locked === null ? $this->police->arrest($userId, $now) : null,
        ];

        if ($locked !== null || $run === null || $car === null) {
            return [...$base, 'car' => null, 'offers' => [], 'opportunity' => null, 'active' => null, 'limits' => null];
        }

        $vehicle = $this->vehicles->vehicle($run->car_slug) ?? [];
        $stats = $this->statsOf($run);
        $active = $this->activeOf($run->id);
        $limits = $this->limits($userId, $level, $now);
        $bonusPpm = $this->vipBonusPpm($userId, $now);
        $busy = match (true) {
            $active !== null => 'gig_busy',
            $run->status !== RaceRun::STATUS_RUNNING || $this->garages->hasWork($car) => 'car_cannot_travel',
            $limits['daily_used'] >= $limits['daily_limit'] => 'gig_daily_limit',
            default => null,
        };
        $taken = Gig::query()->where('user_id', $userId)->pluck('offer_key')->all();
        $offers = [];

        foreach ($this->board->offers($this->seed($userId), $region, $now, $level, $this->destinations($region, $level)) as $offer) {
            if (in_array($offer->key, $taken, true)) {
                continue;
            }

            $quote = $this->quote($offer, $vehicle, $stats, $level, $bonusPpm);
            $blocked = $this->staticBlock($offer, $vehicle, $quote) ?? ($busy !== null ? ['code' => $busy, 'details' => []] : null);

            if ($blocked === null && $run->fuel_ml < $quote['fuel_ml']) {
                $blocked = ['code' => 'gig_no_fuel', 'details' => $this->fuelDetails($quote['fuel_ml'], $run->fuel_ml)];
            }

            $offers[] = $this->offerView($offer, $quote, $blocked);
        }

        $opportunity = null;
        $night = $this->board->opportunity($this->seed($userId), $region, $now, $level, $this->destinations($region, $level));

        if ($night !== null && ! in_array($night->key, $taken, true)) {
            $quote = $this->quote($night, $vehicle, $stats, $level, $bonusPpm);
            $blocked = $this->staticBlock($night, $vehicle, $quote) ?? ($busy !== null ? ['code' => $busy, 'details' => []] : null);

            if ($blocked === null && $limits['night_used'] >= $limits['night_limit']) {
                $blocked = ['code' => 'gig_night_limit', 'details' => ['limit' => $limits['night_limit']]];
            }

            if ($blocked === null && $base['arrest'] !== null) {
                $blocked = ['code' => 'arrested', 'details' => ['until_ms' => $base['arrest']['until_ms']]];
            }

            if ($blocked === null && $run->fuel_ml < $quote['fuel_ml']) {
                $blocked = ['code' => 'gig_no_fuel', 'details' => $this->fuelDetails($quote['fuel_ml'], $run->fuel_ml)];
            }

            $opportunity = [...$this->offerView($night, $quote, $blocked), 'night' => $this->nightView($night, $vehicle, $stats, $level, $userId, $now)];
        }

        return [
            ...$base,
            'car' => [
                'id' => $car->id,
                'seats' => (int) ($vehicle['seats'] ?? 0),
                'cargo_kg' => (int) ($vehicle['cargo_kg'] ?? 0),
                'class' => $vehicle['class'] ?? null,
                'speed_kmh' => (int) round($this->tripSpeedKmh($stats)),
            ],
            'offers' => $offers,
            'opportunity' => $opportunity,
            'active' => $active === null ? null : $this->gigView($active, $now),
            'limits' => $limits,
        ];
    }

    /**
     * Takes an offer of the board with a car: the car earns until it leaves, then stands for the time of the trip with its fuel out of the tank. Taking the same offer again
     * changes nothing (`repeated`).
     *
     * @param  int|null  $carId  the car, or the one the driver plays when left out
     * @return array{gig: Gig, repeated: bool}
     *
     * @throws RuleRefusedException `gigs_unavailable`, `arrested`, `no_run` or `car_not_found`, `gig_gone`, `gig_requirements`, `gig_deadline`, `gig_busy`, `car_cannot_travel`, `gig_no_fuel`, `gig_daily_limit` or `gig_night_limit` (nothing is changed)
     */
    public function take(int $userId, string $offerKey, ?int $carId = null, ?int $nowMs = null): array
    {
        return $this->locked($userId, $nowMs, function (Collection $runs, int $now) use ($userId, $offerKey, $carId): array {
            // What is due comes first: a car that is back is on its track, and its gig is paid.
            $this->finishDue($userId, $runs, $now);

            if ($this->lockedBy($userId) !== null) {
                throw new RuleRefusedException('gigs_unavailable');
            }

            // A driver under arrest takes no gig; the gig they took before is not undone, the car comes back and the arrest is a matter of the driver.
            $arrestedUntil = $this->police->arrestedUntilMs($userId, $now);

            if ($arrestedUntil !== null) {
                throw new RuleRefusedException('arrested', ['until_ms' => $arrestedUntil]);
            }

            $car = $carId === null ? $this->garage->carOf($userId) : Car::query()->where('user_id', $userId)->find($carId);
            $run = $car === null ? null : $runs->get($car->id);

            if ($car === null || $run === null) {
                throw new RuleRefusedException($carId === null ? 'no_run' : 'car_not_found');
            }

            $taken = Gig::query()->where('user_id', $userId)->where('offer_key', $offerKey)->first();

            if ($taken !== null) {
                // The offer that the driver took back is spent: it is not on the board and is not theirs to take again.
                return $taken->status === Gig::STATUS_CANCELLED ? throw new RuleRefusedException('gig_gone') : ['gig' => $taken, 'repeated' => true];
            }

            $level = $this->rules->playerLevelAt($this->ledger->xp($userId));
            $region = $this->garage->cityOf($run);
            $offer = $this->board->find($offerKey, $this->seed($userId), $now, $level, $this->destinations($region, $level));

            if ($offer === null || $offer->region !== $region) {
                throw new RuleRefusedException('gig_gone');
            }

            $vehicle = $this->vehicles->vehicle($run->car_slug) ?? [];
            $quote = $this->quote($offer, $vehicle, $this->statsOf($run), $level, $this->vipBonusPpm($userId, $now));
            $blocked = $this->staticBlock($offer, $vehicle, $quote);

            if ($blocked !== null) {
                throw new RuleRefusedException($blocked['code'], $blocked['details']);
            }

            if ($this->activeOf($run->id) !== null) {
                throw new RuleRefusedException('gig_busy');
            }

            $limits = $this->limits($userId, $level, $now);

            if ($limits['daily_used'] >= $limits['daily_limit']) {
                throw new RuleRefusedException('gig_daily_limit', ['limit' => $limits['daily_limit'], 'resets_ms' => $limits['resets_ms']]);
            }

            if ($offer->opportunity && $limits['night_used'] >= $limits['night_limit']) {
                throw new RuleRefusedException('gig_night_limit', ['limit' => $limits['night_limit'], 'resets_ms' => $limits['resets_ms']]);
            }

            // The car earns until it leaves, and a car that cannot go on (the tank ran dry, it broke down, the workshop has work for it) stays where it is.
            $this->runs->settleLocked($run, $now);

            if ($run->status !== RaceRun::STATUS_RUNNING || $this->garages->hasWork($car)) {
                throw new RuleRefusedException('car_cannot_travel');
            }

            if ($run->fuel_ml < $quote['fuel_ml']) {
                throw new RuleRefusedException('gig_no_fuel', $this->fuelDetails($quote['fuel_ml'], $run->fuel_ml));
            }

            $this->runs->startGig($run, $quote['fuel_ml']);

            $gig = Gig::query()->create([
                ...$this->policeOf($offer, $vehicle, $quote, $run, $userId, $now),
                'user_id' => $userId,
                'race_run_id' => $run->id,
                'offer_key' => $offer->key,
                'template' => $offer->template,
                'kind' => $offer->kind,
                'region' => $offer->region,
                'to_region' => $offer->toRegion,
                'distance_km' => $offer->km,
                'amount' => $offer->amount,
                'capacity' => $offer->capacity,
                'rarity' => $offer->rarity,
                'phase' => $offer->phase,
                'fuel_ml' => $quote['fuel_ml'],
                'start_ms' => $now,
                'finish_ms' => $now + $quote['duration_ms'],
                'reward_cents' => $quote['reward_cents'],
                'bonus_cents' => $quote['bonus_cents'],
                'xp' => $quote['xp'],
                'car_xp' => $quote['car_xp'],
                'status' => Gig::STATUS_ACTIVE,
            ]);

            return ['gig' => $gig, 'repeated' => false];
        });
    }

    /**
     * Takes a gig back in the first seconds after it was taken (`cancel_window_ms`): the fuel goes back into the tank, the car is on its track at once, nothing is paid,
     * and the offer is spent.
     *
     * @throws RuleRefusedException `gig_not_found` (not theirs, not on the road, or already finished) or `gig_cancel_window` (too late)
     */
    public function cancel(int $userId, int $gigId, ?int $nowMs = null): Gig
    {
        return $this->locked($userId, $nowMs, function (Collection $runs, int $now) use ($userId, $gigId): Gig {
            // A gig whose time is over has been paid, and is not theirs to take back.
            $this->finishDue($userId, $runs, $now);

            $gig = Gig::query()->where('user_id', $userId)->where('status', Gig::STATUS_ACTIVE)->lockForUpdate()->find($gigId) ?? throw new RuleRefusedException('gig_not_found');

            if ($now > $gig->start_ms + $this->catalog->cancelWindowMs()) {
                throw new RuleRefusedException('gig_cancel_window', ['window_ms' => $this->catalog->cancelWindowMs()]);
            }

            $run = $runs->firstWhere('id', $gig->race_run_id);

            if ($run !== null) {
                $this->runs->finishGig($run, $this->inputs->forRun($run, $run->track_slug), $now, $gig->fuel_ml);
            }

            $gig->forceFill(['status' => Gig::STATUS_CANCELLED, 'done_ms' => $now])->save();

            return $gig;
        });
    }

    /**
     * What a gig would be for a car: the time it takes, the fuel it burns, what it pays and gives. The pay does not depend on the car but through its rung of the ladder (the
     * level at which its model is sold); the time does, through its speed (not above the limit of the roads).
     *
     * @param  array<string, mixed>  $vehicle  the definition of the car in the pack
     * @param  int|null  $payPpm  the pay of the template when it is not the offer's own (the plain pay of the same trip, to put beside a night run)
     * @return array{duration_ms: int, fuel_ml: int, reward_cents: int, bonus_cents: int, xp: int, car_xp: int}
     */
    public function quote(GigOffer $offer, array $vehicle, CarStats $stats, int $level, int $vipBonusPpm = 0, ?int $payPpm = null): array
    {
        $speed = $this->tripSpeedKmh($stats);
        $band = $this->catalog->band($level);
        $anchor = $this->catalog->anchorCentsPerHour((int) ($vehicle['min_player_level'] ?? 1));
        $rarity = $this->catalog->rarity($offer->rarity)['pay_ppm'] ?? Ppm::ONE;
        $phasePpm = $this->catalog->template($offer->template)['phase_pay_ppm'][$offer->phase] ?? Ppm::ONE;

        // The pay of the reference time of the trip, a part at a time and each part rounded, so that the number is the same on every machine.
        $perHour = Ppm::mul(Ppm::mul(Ppm::mul(Ppm::mul($anchor, $band['ratio_ppm']), $payPpm ?? $offer->payPpm), $phasePpm), $rarity);
        $base = intdiv($perHour * $offer->refMs + 1_800_000, 3_600_000);
        $bonus = $vipBonusPpm > 0 ? Ppm::mul($base, $vipBonusPpm) : 0;

        return [
            'duration_ms' => max(1, (int) round($offer->km / $speed * 3_600_000)),
            'fuel_ml' => max(1, (int) ceil($offer->km * $this->rules->travelFuelMlPerKm() * $stats->fuelUseMultiplier)),
            'reward_cents' => $base + $bonus,
            'bonus_cents' => $bonus,
            'xp' => intdiv($this->catalog->xpPerHour() * $offer->refMs, 3_600_000),
            'car_xp' => intdiv($this->catalog->carXpPerHour() * $offer->refMs, 3_600_000),
        ];
    }

    /** How fast a car drives on a gig, in km/h: its speed on the road (the share of its top speed that a journey uses), not above the limit of the roads. */
    public function tripSpeedKmh(CarStats $stats): float
    {
        return min($stats->get('top_speed') * $this->rules->travelSpeedSharePpm() / 1_000_000, (float) $this->catalog->speedCapKmh());
    }

    /**
     * Why a car cannot take an offer whatever it is doing now: it has too few seats or too little room, it is not of the class that is asked for, or it would not be there
     * in time.
     *
     * @param  array<string, mixed>  $vehicle
     * @param  array{duration_ms: int}  $quote
     * @return array{code: string, details: array<string, mixed>}|null
     */
    public function staticBlock(GigOffer $offer, array $vehicle, array $quote): ?array
    {
        $have = (int) ($vehicle[$offer->capacity] ?? 0);

        if ($have < $offer->amount) {
            return ['code' => 'gig_requirements', 'details' => ['what' => $offer->capacity, 'need' => $offer->amount, 'have' => $have]];
        }

        if ($offer->classIn !== null && ! in_array($vehicle['class'] ?? '', $offer->classIn, true)) {
            return ['code' => 'gig_requirements', 'details' => ['what' => 'class', 'need' => implode(', ', $offer->classIn), 'have' => (string) ($vehicle['class'] ?? '')]];
        }

        if ($offer->deadlinePpm !== null && $quote['duration_ms'] > Ppm::mul($offer->refMs, $offer->deadlinePpm)) {
            return ['code' => 'gig_deadline', 'details' => ['need_ms' => Ppm::mul($offer->refMs, $offer->deadlinePpm), 'have_ms' => $quote['duration_ms']]];
        }

        return null;
    }

    /**
     * The cities a gig can go to from a city: the ones the level of the driver has opened and a road leads to, with the kilometres of the way.
     *
     * @return list<array{region: string, km: int}>
     */
    public function destinations(string $region, int $level): array
    {
        $roads = new RoadMap($this->rules->roads());
        $cities = [];

        foreach (array_keys($this->rules->regions()) as $slug) {
            $km = $slug === $region ? null : $roads->distanceKm($region, $slug);

            if ($km !== null && $level >= $this->rules->cityMinPlayerLevel($slug)) {
                $cities[] = ['region' => $slug, 'km' => $km];
            }
        }

        return $cities;
    }

    /**
     * How many gigs the driver has taken today, how many they may, and when the day of the limit turns over.
     *
     * @return array{daily_used: int, daily_limit: int, night_used: int, night_limit: int, resets_ms: int}
     */
    public function limits(int $userId, int $level, int $nowMs): array
    {
        $zone = new DateTimeZone($this->catalog->timezone());
        $moment = (new DateTimeImmutable('@'.intdiv($nowMs, 1000)))->setTimezone($zone);
        $start = $moment->setTime($this->catalog->resetHour(), 0);

        if ($start > $moment) {
            $start = $start->modify('-1 day');
        }

        $startMs = $start->getTimestamp() * 1000;

        $night = $this->catalog->night();

        return [
            'daily_used' => Gig::query()->where('user_id', $userId)->where('status', '!=', Gig::STATUS_CANCELLED)->where('start_ms', '>=', $startMs)->count(),
            'daily_limit' => $this->catalog->band($level)['daily_limit'],
            'night_used' => $night === null ? 0 : Gig::query()->where('user_id', $userId)->where('kind', $night['kind'])->where('status', '!=', Gig::STATUS_CANCELLED)->where('start_ms', '>=', $startMs)->count(),
            'night_limit' => $night['daily_limit'] ?? 0,
            'resets_ms' => $start->modify('+1 day')->getTimestamp() * 1000,
        ];
    }

    /** The bonus of the cash of a VIP account at this moment, in ppm: nothing when the driver has none, or the game does not give it. */
    private function vipBonusPpm(int $userId, int $nowMs): int
    {
        $vip = $this->rules->vip();
        $until = Wallet::query()->where('user_id', $userId)->first()?->vip_until?->getTimestampMs();

        return $vip !== null && $vip['enabled'] && $until !== null && $until > $nowMs ? $vip['cash_bonus_ppm'] : 0;
    }

    /**
     * The gigs whose time is over: each pays its reward once, adds the kilometres to the car, puts it back on its track from the moment it was back and gives the experience.
     *
     * @param  Collection<int, RaceRun>  $runs  the runs of the driver, by car
     */
    private function finishDue(int $userId, Collection $runs, int $now): void
    {
        $due = Gig::query()->where('user_id', $userId)->where('status', Gig::STATUS_ACTIVE)->where('finish_ms', '<=', $now)->orderBy('finish_ms')->lockForUpdate()->get();

        foreach ($due as $gig) {
            $run = $runs->firstWhere('id', $gig->race_run_id);

            // What a gig pays is frozen when it is taken: a night run that was stopped pays less, or nothing, whatever the rules say later.
            $paid = (int) ($gig->paid_cents ?? $gig->reward_cents);
            $arrested = $gig->police_outcome === Gig::POLICE_ARRESTED;
            $finePaid = null;

            if ($paid > 0) {
                $this->ledger->post($userId, $paid, 'gig', "gig:{$gig->id}");
            }

            // The fine is taken from what the driver has: a driver with little does not go below zero.
            if ($arrested && (int) $gig->fine_cents > 0) {
                $finePaid = min((int) $gig->fine_cents, max(0, $this->ledger->balance($userId)));

                if ($finePaid > 0) {
                    $this->ledger->post($userId, -$finePaid, 'gig_fine', "gig:{$gig->id}:fine");
                }
            }

            if (! $arrested) {
                $this->ledger->grantXp($userId, $gig->xp);
            }

            if ($run !== null) {
                // The kilometres of the trip are on the odometer and on the parts, whatever the run was frozen with when the car left.
                if ($run->car_id !== null) {
                    (new CarWear)->applyDistance($run->car_id, $run->config, $gig->distance_km);
                    $run->unsetRelation('car');
                    $arrested || $this->progress->award($run->car_id, $gig->car_xp);
                }

                $this->runs->finishGig($run, $this->inputs->forRun($run, $run->track_slug), $gig->finish_ms);
            }

            $gig->forceFill(['status' => Gig::STATUS_DONE, 'done_ms' => $gig->finish_ms, 'paid_cents' => $paid, 'fine_paid_cents' => $finePaid])->save();
        }
    }

    /** The title of a template in the language of the driver; the night run is not one of the templates of the document, it has its own. */
    private function titleOf(string $template): string
    {
        $title = $template === GigCatalog::NIGHT ? ($this->catalog->night()['title'] ?? []) : ($this->catalog->template($template)['title'] ?? []);

        return I18nText::from($title)->for();
    }

    /**
     * What the police make of a gig, to freeze in its row when it is taken: nothing for a gig that is not a night run. A night run has its chance, its end (drawn now), the moment
     * of the stop (in the middle of the trip), the fine and the bail, and what it will be paid, so that nothing that the rules say later changes it.
     *
     * @param  array<string, mixed>  $vehicle
     * @param  array{duration_ms: int, reward_cents: int, bonus_cents: int}  $quote
     * @return array<string, int|string|null>
     */
    private function policeOf(GigOffer $offer, array $vehicle, array $quote, RaceRun $run, int $userId, int $now): array
    {
        $night = $offer->opportunity ? $this->catalog->night() : null;

        if ($night === null) {
            return ['paid_cents' => $quote['reward_cents']];
        }

        $odds = $this->police->odds((string) ($vehicle['class'] ?? ''), $this->police->heated($userId, $offer->region, $now)) ?? throw new RuleRefusedException('gig_gone');
        $outcome = $this->police->outcome($odds, $run->id, $now, $offer->key);
        $plain = $quote['reward_cents'] - $quote['bonus_cents'];
        $paid = match ($outcome) {
            Gig::POLICE_CLEAN => $quote['reward_cents'],
            Gig::POLICE_ESCAPED => Ppm::mul($quote['reward_cents'], $night['escape_pay_ppm']),
            default => 0,
        };
        $stopMs = $outcome === Gig::POLICE_CLEAN ? null : $now + intdiv($quote['duration_ms'], 2);

        return [
            'police_control_ppm' => $odds['control_ppm'],
            'police_outcome' => $outcome,
            'police_at_ms' => $stopMs,
            'arrest_until_ms' => $outcome === Gig::POLICE_ARRESTED ? $stopMs + $night['arrest']['ms'] : null,
            'fine_cents' => $outcome === Gig::POLICE_ARRESTED ? Ppm::mul($plain, $night['arrest']['fine_ppm']) : null,
            'bail_cents' => $outcome === Gig::POLICE_ARRESTED ? Ppm::mul($plain, $night['arrest']['bail_ppm']) : null,
            'paid_cents' => $paid,
        ];
    }

    /**
     * What a night run is, for the driver who is looking at it: what the plain pay of the same trip is, the chances of every end, what each end costs, and how loud the car is. The
     * chances are the ones the draw is made with when it is taken.
     *
     * @param  array<string, mixed>  $vehicle
     * @return array<string, mixed>
     */
    private function nightView(GigOffer $offer, array $vehicle, CarStats $stats, int $level, int $userId, int $now): array
    {
        $night = $this->catalog->night() ?? [];
        $heated = $this->police->heated($userId, $offer->region, $now);
        $plain = $this->quote($offer, $vehicle, $stats, $level, 0, Ppm::ONE);
        $full = $this->quote($offer, $vehicle, $stats, $level)['reward_cents'];

        return [
            'multiplier_ppm' => $offer->payPpm,
            'plain_reward_cents' => $plain['reward_cents'],
            'escape_pay_ppm' => $night['escape_pay_ppm'] ?? 0,
            'arrest_ms' => $night['arrest']['ms'] ?? 0,
            'fine_cents' => Ppm::mul($full, $night['arrest']['fine_ppm'] ?? 0),
            'bail_cents' => Ppm::mul($full, $night['arrest']['bail_ppm'] ?? 0),
            'heated' => $heated,
            'odds' => $this->police->odds((string) ($vehicle['class'] ?? ''), $heated),
        ];
    }

    /**
     * What the police made of a gig, once the stop has happened: before it the driver cannot know (the end is in the row, drawn, and is not told).
     *
     * @return array{outcome: string, at_ms: int, fine_cents: int, bail_cents: int, arrest_until_ms: int|null, bailed: bool, paid_cents: int}|null
     */
    private function policeView(Gig $gig, int $now): ?array
    {
        if ($gig->police_outcome === null || $gig->police_outcome === Gig::POLICE_CLEAN || $gig->police_at_ms === null || $now < $gig->police_at_ms) {
            return null;
        }

        return [
            'outcome' => $gig->police_outcome,
            'at_ms' => (int) $gig->police_at_ms,
            'fine_cents' => (int) $gig->fine_cents,
            'bail_cents' => (int) $gig->bail_cents,
            'arrest_until_ms' => $gig->arrest_until_ms,
            'bailed' => $gig->bailed_ms !== null,
            'paid_cents' => (int) ($gig->paid_cents ?? 0),
        ];
    }

    private function statsOf(RaceRun $run): CarStats
    {
        return $this->inputs->stats($run->car_slug, $run->car?->mountedParts() ?? []);
    }

    /** The seed of the board of a driver: what makes it theirs, and not something that can be worked out from outside. */
    private function seed(int $userId): string
    {
        return hash_hmac('sha256', "gig|{$userId}", (string) config('app.key'));
    }

    /** @return array{required: string, available: string} */
    private function fuelDetails(int $requiredMl, int $availableMl): array
    {
        return ['required' => number_format($requiredMl / 1000, 1, '.', ''), 'available' => number_format($availableMl / 1000, 1, '.', '')];
    }

    /**
     * @param  array{duration_ms: int, fuel_ml: int, reward_cents: int, bonus_cents: int, xp: int, car_xp: int}  $quote
     * @param  array{code: string, details: array<string, mixed>}|null  $blocked
     * @return array<string, mixed>
     */
    private function offerView(GigOffer $offer, array $quote, ?array $blocked): array
    {
        return [
            'key' => $offer->key,
            'template' => $offer->template,
            'title' => $this->titleOf($offer->template),
            'kind' => $offer->kind,
            'kind_name' => I18nText::from($this->catalog->kinds()[$offer->kind]['name'] ?? [])->for(),
            'region' => $offer->region,
            'to' => $offer->toRegion,
            'to_name' => $offer->toRegion === null ? null : $this->rules->cityName($offer->toRegion),
            'km' => $offer->km,
            'capacity' => $offer->capacity,
            'amount' => $offer->amount,
            'class_in' => $offer->classIn,
            'opportunity' => $offer->opportunity,
            'rarity' => $offer->rarity,
            'phase' => $offer->phase,
            'until_ms' => $offer->untilMs,
            'duration_ms' => $quote['duration_ms'],
            'fuel_ml' => $quote['fuel_ml'],
            'reward_cents' => $quote['reward_cents'],
            'bonus_cents' => $quote['bonus_cents'],
            'xp' => $quote['xp'],
            'eligible' => $blocked === null,
            'blocked' => $blocked,
        ];
    }

    /** @return array<string, mixed> */
    public function gigView(Gig $gig, ?int $nowMs = null): array
    {
        $now = $nowMs ?? RunService::nowMs();

        return [
            'id' => $gig->id,
            'offer' => $gig->offer_key,
            'template' => $gig->template,
            'title' => $this->titleOf($gig->template),
            'kind' => $gig->kind,
            'kind_name' => I18nText::from($this->catalog->kinds()[$gig->kind]['name'] ?? [])->for(),
            'region' => $gig->region,
            'region_name' => $this->rules->cityName($gig->region),
            'to' => $gig->to_region,
            'to_name' => $gig->to_region === null ? null : $this->rules->cityName($gig->to_region),
            'km' => $gig->distance_km,
            'capacity' => $gig->capacity,
            'amount' => $gig->amount,
            'rarity' => $gig->rarity,
            'phase' => $gig->phase,
            'start_ms' => $gig->start_ms,
            'finish_ms' => $gig->finish_ms,
            'cancel_until_ms' => $gig->start_ms + $this->catalog->cancelWindowMs(),
            'reward_cents' => $gig->reward_cents,
            'bonus_cents' => $gig->bonus_cents,
            'xp' => $gig->xp,
            'status' => $gig->status,
            'police' => $this->policeView($gig, $now),
        ];
    }

    /**
     * Runs `$work` with the wallet of the driver and then all their runs locked, always in this order (the runs by id), like the settlement of a run and the journeys, so two
     * requests cannot wait for each other. The clock is read after the locks.
     *
     * @template T
     *
     * @param  callable(Collection<int, RaceRun>, int): T  $work  gets the runs of the driver by car
     * @return T
     */
    private function locked(int $userId, ?int $nowMs, callable $work): mixed
    {
        // Before the transaction: creating the wallet under a lock deadlocks (see Ledger::ensureWallet).
        $this->ledger->ensureWallet($userId);

        return DB::transaction(function () use ($userId, $nowMs, $work): mixed {
            $this->ledger->lockWallet($userId);
            $runs = RaceRun::query()->where('user_id', $userId)->orderBy('id')->lockForUpdate()->get()->keyBy('car_id');

            return $work($runs, $nowMs ?? RunService::nowMs());
        }, Ledger::DEADLOCK_ATTEMPTS);
    }
}
