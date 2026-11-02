<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Exceptions\RuleRefusedException;
use App\Models\Car;
use App\Models\LedgerEntry;
use App\Models\RaceRun;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WorkshopJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The cars that drive: a driver has only so many cars active at a time (`slots` of the rules: a base, and more with the VIP account), and the others stand in the
 * garage in storage. A car in storage keeps its run and its place in the garage, but the run stands with the stop `stored`, so nothing is settled past it: it does not
 * drive, burns no fuel and wears nothing, and earns nothing. An active car has a number, its slot, which is kept in order with no gaps (the car that was put on the road
 * first is number 1).
 *
 * Nothing is taken from a driver when the limit is made: the cars that drive stay on the road, and the driver keeps up to as many of them as they had (`legacy_slots`
 * of the wallet, worked out the first time anybody asks and only ever falling: every car the driver puts in storage or sells that takes them under the limit takes
 * the slot away). Everything that changes what drives runs under the lock of the wallet, after the car has been settled, so what it earned until then is the driver's.
 */
final class Slots
{
    public function __construct(
        private readonly Rules $rules,
        private readonly Ledger $ledger,
        private readonly RunService $runs,
        private readonly Garage $garage,
        private readonly MissionScale $scale,
    ) {}

    /** Whether the game limits the cars that drive. */
    public function on(): bool
    {
        return $this->rules->slotsOn();
    }

    /**
     * How many cars a driver can have on the road now: the base, the slots of the VIP account while it lasts, and the cars they keep from before the limit.
     *
     * @return array{base: int, vip: int, vip_active: bool, legacy: int, total: int}
     */
    public function limits(int $userId): array
    {
        $settings = $this->rules->slots() ?? ['base' => PHP_INT_MAX, 'vip' => 0];
        $vipActive = $this->vipActive($userId);
        $vip = $vipActive ? $settings['vip'] : 0;
        $legacy = $this->legacy($userId, $settings['base'] + $vip);

        return ['base' => $settings['base'], 'vip' => $settings['vip'], 'vip_active' => $vipActive, 'legacy' => $legacy, 'total' => $settings['base'] + $vip + $legacy];
    }

    /**
     * What the driver has, for the screens: the limit and how it is made up, how many cars are on the road and in storage, how many slots are free, and what a swap
     * costs. The cars are put in order first (a car that had no number gets one).
     *
     * @return array{base: int, vip: int, vip_active: bool, legacy: int, limit: int, active: int, stored: int, free: int, swap_ms: int, swap_fee_cents: int}|null null when the game has no limit
     */
    public function state(int $userId): ?array
    {
        if (! $this->on()) {
            return null;
        }

        $this->ledger->ensureWallet($userId);
        $this->renumber($userId);

        $limits = $this->limits($userId);
        $active = $this->activeCars($userId)->count();
        $all = Car::query()->where('user_id', $userId)->count();

        return [
            'base' => $limits['base'],
            'vip' => $limits['vip'],
            'vip_active' => $limits['vip_active'],
            'legacy' => $limits['legacy'],
            'limit' => $limits['total'],
            'active' => $active,
            'stored' => $all - $active,
            'free' => max(0, $limits['total'] - $active),
            'swap_ms' => $this->rules->slots()['swap_ms'],
            'swap_fee_cents' => $this->swapFeeCents($userId),
        ];
    }

    /**
     * Whether a car that the driver gets now has to go to storage: all the slots are taken. The caller holds the lock of the wallet. A game with no limit never stores.
     */
    public function mustStore(int $userId): bool
    {
        if (! $this->on()) {
            return false;
        }

        $this->ledger->ensureWallet($userId);
        $this->renumber($userId);

        return $this->activeCars($userId)->count() >= $this->limits($userId)['total'];
    }

    /** The cars of the driver that are on the road, in the order of their slots (the ones that have no number yet last). @return Collection<int, Car> */
    public function activeCars(int $userId): Collection
    {
        $stored = $this->storedCarIds($userId);

        return Car::query()->where('user_id', $userId)->get()
            ->reject(fn (Car $car): bool => in_array($car->id, $stored, true))
            ->sortBy(fn (Car $car): array => [$car->slot_no ?? PHP_INT_MAX, $car->id])
            ->values();
    }

    /** @return list<int> the cars of the driver that are in storage */
    public function storedCarIds(int $userId): array
    {
        return array_map('intval', RaceRun::query()->where('user_id', $userId)->where('stop_reason', RaceRun::STOP_STORED)->whereNotNull('car_id')->pluck('car_id')->all());
    }

    /**
     * Gives the cars that drive their numbers, in order and with no gaps: the cars that have one keep their order, the ones that have none follow (the car that is played
     * first), and a car in storage has none. Cheap when nothing changed.
     */
    public function renumber(int $userId): void
    {
        if (! $this->on()) {
            return;
        }

        $selected = User::query()->whereKey($userId)->value('selected_car_id');
        $active = $this->activeCars($userId)
            ->sortBy(fn (Car $car): array => [$car->slot_no ?? PHP_INT_MAX, $car->slot_no === null && $car->id !== $selected ? 1 : 0, $car->id])
            ->values();

        foreach ($active as $index => $car) {
            if ($car->slot_no !== $index + 1) {
                $car->forceFill(['slot_no' => $index + 1])->save();
            }
        }

        Car::query()->where('user_id', $userId)->whereNotNull('slot_no')->whereNotIn('id', $active->pluck('id')->all())->update(['slot_no' => null]);
    }

    /**
     * Puts a car of the driver in storage. The car is settled first, so what it earned until now is paid; its run then stands with the stop `stored`. A car in storage
     * already stays there (`repeated`).
     *
     * @return array{repeated: bool}
     *
     * @throws RuleRefusedException `slots_unavailable`, `car_not_found`, `car_busy` (on the road or with work waiting in the workshop) or `last_active_car`
     */
    public function store(int $userId, int $carId): array
    {
        $this->ensureOn();
        $this->ledger->ensureWallet($userId);

        return DB::transaction(function () use ($userId, $carId): array {
            $this->ledger->lockWallet($userId);

            $car = $this->carOf($userId, $carId);
            $run = $this->garage->runOf($car);

            if ($run?->stop_reason === RaceRun::STOP_STORED) {
                return ['repeated' => true];
            }

            $this->renumber($userId);

            if ($this->activeCars($userId)->count() <= 1) {
                throw new RuleRefusedException('last_active_car');
            }

            $this->assertNotBusy($car, $run);
            $this->putInStorage($userId, $car);
            $this->afterLeaving($userId);

            return ['repeated' => false];
        }, Ledger::DEADLOCK_ATTEMPTS);
    }

    /**
     * Puts a car from storage on the road, in the next slot: it drives from the moment it is ready (`swap_ms` from now). A car on the road already stays on it
     * (`repeated`).
     *
     * @return array{repeated: bool}
     *
     * @throws RuleRefusedException `slots_unavailable`, `car_not_found` or `no_free_slot`
     */
    public function activate(int $userId, int $carId): array
    {
        $this->ensureOn();
        $this->ledger->ensureWallet($userId);

        return DB::transaction(function () use ($userId, $carId): array {
            $this->ledger->lockWallet($userId);

            $car = $this->carOf($userId, $carId);
            $run = $this->garage->runOf($car);

            if ($run?->stop_reason !== RaceRun::STOP_STORED) {
                return ['repeated' => true];
            }

            $this->renumber($userId);
            $limits = $this->limits($userId);

            if ($this->activeCars($userId)->count() >= $limits['total']) {
                throw new RuleRefusedException('no_free_slot', ['limit' => $limits['total']]);
            }

            $this->putOnTheRoad($userId, $car, $run);

            return ['repeated' => false];
        }, Ledger::DEADLOCK_ATTEMPTS);
    }

    /**
     * Swaps a car on the road for one from storage: the first is put in storage (after it is settled), the second on the road, and the swap is paid for (a part of a tank
     * of the best car, `slots.swap_fee_ppm`). The `$requestId` is one intention: sent again it changes and charges nothing (`repeated`).
     *
     * @return array{repeated: bool, fee_cents: int}
     *
     * @throws RuleRefusedException `slots_unavailable`, `car_not_found`, `car_not_stored`, `car_not_active`, `car_busy` or `insufficient_funds` (nothing is changed)
     */
    public function swap(int $userId, int $carId, int $withId, string $requestId): array
    {
        $this->ensureOn();
        $this->ledger->ensureWallet($userId);

        return DB::transaction(function () use ($userId, $carId, $withId, $requestId): array {
            $this->ledger->lockWallet($userId);

            $key = "swap:{$userId}:{$requestId}";

            if (LedgerEntry::query()->where('idem_key', $key)->exists()) {
                return ['repeated' => true, 'fee_cents' => 0];
            }

            $car = $this->carOf($userId, $carId);
            $with = $this->carOf($userId, $withId);
            $run = $this->garage->runOf($car);
            $withRun = $this->garage->runOf($with);

            if ($run?->stop_reason !== RaceRun::STOP_STORED) {
                throw new RuleRefusedException('car_not_stored');
            }

            if ($withRun?->stop_reason === RaceRun::STOP_STORED) {
                throw new RuleRefusedException('car_not_active');
            }

            $this->assertNotBusy($with, $withRun);

            $fee = $this->swapFeeCents($userId);

            if ($fee > 0) {
                $this->ledger->post($userId, -$fee, 'swap', $key);
            }

            $this->putInStorage($userId, $with);
            $run = $this->garage->runOf($car);
            $this->putOnTheRoad($userId, $car, $run);
            $this->afterLeaving($userId);

            return ['repeated' => false, 'fee_cents' => $fee];
        }, Ledger::DEADLOCK_ATTEMPTS);
    }

    /**
     * A car of the driver left the road (sold, or put in storage): the cars that drive are put in order, the driver's selection is moved to one that drives, and the cars they
     * kept from before the limit are as many as it still lets them keep. The caller holds the lock of the wallet.
     */
    public function afterLeaving(int $userId): void
    {
        if (! $this->on()) {
            return;
        }

        $this->renumber($userId);

        $selected = User::query()->whereKey($userId)->value('selected_car_id');
        $active = $this->activeCars($userId);

        if ($selected !== null && ! $active->contains('id', $selected) && $active->isNotEmpty()) {
            User::query()->whereKey($userId)->update(['selected_car_id' => $active->first()->id]);
        }

        $limits = $this->limits($userId);
        $allowed = $limits['base'] + ($limits['vip_active'] ? $limits['vip'] : 0);
        $legacy = min($limits['legacy'], max(0, $active->count() - $allowed));

        if ($legacy !== $limits['legacy']) {
            Wallet::query()->whereKey($userId)->update(['legacy_slots' => $legacy]);
        }
    }

    /**
     * Until when a run may earn, or null when there is no end to it. A car in a slot that only the VIP account gives (a number above the base and the cars the driver kept
     * from before the limit) earns until the account ends and not a moment after, so a driver who is away when it ends does not get the time for nothing; a driver who never
     * had the account has no end to it. A car that has no number yet is put in order first (it is the first time anybody looks at the cars of the driver since the limit was made).
     */
    public function horizonMs(RaceRun $run): ?int
    {
        if (! $this->on() || $run->car_id === null || $run->stop_reason === RaceRun::STOP_STORED) {
            return null;
        }

        $slot = Car::query()->whereKey($run->car_id)->value('slot_no');

        if ($slot === null) {
            $this->ledger->ensureWallet($run->user_id);
            $this->renumber($run->user_id);
            $slot = Car::query()->whereKey($run->car_id)->value('slot_no');
        }

        if ($slot === null || $slot <= $this->rules->slots()['base']) {
            return null;
        }

        $limits = $this->limits($run->user_id);

        if ($slot <= $limits['base'] + $limits['legacy']) {
            return null;
        }

        // A driver who never had the account has no end to wait for: the cars they have above the limit were not given by it, and are theirs to keep.
        return Wallet::query()->find($run->user_id)?->vip_until?->getTimestampMs();
    }

    /**
     * The account that gave the slot of a car has ended: the car goes to storage, from the moment it ended (its run was settled up to it), and the cars that are left are put in
     * order. The caller holds the lock of the wallet and the run.
     */
    public function storeExpired(RaceRun $run): void
    {
        $run->forceFill(['status' => RaceRun::STATUS_STOPPED, 'stop_reason' => RaceRun::STOP_STORED, 'state_version' => $run->state_version + 1])->save();
        Car::query()->whereKey($run->car_id)->update(['slot_no' => null]);

        $this->afterLeaving($run->user_id);
    }

    /** What a swap costs the driver now, in cents: a part of a tank of their best car (whole dollars, and at least a dollar where the rules ask for anything). */
    public function swapFeeCents(int $userId): int
    {
        $ppm = $this->rules->slots()['swap_fee_ppm'] ?? 0;
        $tankful = $ppm > 0 ? $this->scale->tankful($userId) : null;

        return $tankful === null ? 0 : max(100, intdiv((int) round(max(0, $tankful['net_cents']) * $ppm / 1_000_000) + 50, 100) * 100);
    }

    private function ensureOn(): void
    {
        if (! $this->on()) {
            throw new RuleRefusedException('slots_unavailable');
        }
    }

    private function carOf(int $userId, int $carId): Car
    {
        return Car::query()->where('user_id', $userId)->find($carId) ?? throw new RuleRefusedException('car_not_found');
    }

    /** A car on the road to another city, or with work waiting for the mechanic, is not put in storage (as it is not sold). */
    private function assertNotBusy(Car $car, ?RaceRun $run): void
    {
        if ($run?->stop_reason === RaceRun::STOP_TRAVEL || WorkshopJob::query()->where('car_id', $car->id)->where('status', '!=', WorkshopJob::STATUS_DONE)->exists()) {
            throw new RuleRefusedException('car_busy');
        }
    }

    /** Settles the car, so that what it earned until now is the driver's, and then stops its run for storage. */
    private function putInStorage(int $userId, Car $car): void
    {
        $run = $this->garage->runOf($car);

        if ($run !== null) {
            $this->runs->settle($run->id);
            $run = $this->garage->runOf($car);
            $run?->forceFill(['status' => RaceRun::STATUS_STOPPED, 'stop_reason' => RaceRun::STOP_STORED, 'state_version' => $run->state_version + 1])->save();
        }

        $car->forceFill(['slot_no' => null])->save();
    }

    /** Puts the run of a car in storage back on the road, ready after the time of the rules, in the last slot. */
    private function putOnTheRoad(int $userId, Car $car, ?RaceRun $run): void
    {
        $run?->forceFill([
            'status' => RaceRun::STATUS_RUNNING,
            'stop_reason' => null,
            'cursor_ms' => RunService::nowMs() + $this->rules->slots()['swap_ms'],
            'state_version' => $run->state_version + 1,
        ])->save();

        $car->forceFill(['slot_no' => $this->activeCars($userId)->reject(fn (Car $other): bool => $other->id === $car->id)->count() + 1])->save();
    }

    private function vipActive(int $userId): bool
    {
        $until = Wallet::query()->find($userId)?->vip_until;

        return $this->rules->vipOn() && $until !== null && $until->isFuture();
    }

    /** How many cars a driver may keep on the road above `$allowed` because they had them before the limit: worked out the first time, and only ever falling. */
    private function legacy(int $userId, int $allowed): int
    {
        $wallet = Wallet::query()->find($userId);

        if ($wallet === null) {
            return 0;
        }

        if ($wallet->legacy_slots === null) {
            $legacy = max(0, $this->activeCars($userId)->count() - $allowed);

            Wallet::query()->whereKey($userId)->whereNull('legacy_slots')->update(['legacy_slots' => $legacy]);

            return $legacy;
        }

        return (int) $wallet->legacy_slots;
    }
}
