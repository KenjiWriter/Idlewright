<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Catalog;
use App\Content\Rules;
use App\Domain\Market\Affixes;
use App\Domain\Run\Rarity;
use App\Exceptions\RuleRefusedException;
use App\Http\Resources\PartResource;
use App\Models\Part;
use App\Models\PartTransmutation;
use App\Models\WorkshopJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Transmutation of the rarity of a part (Systemy IV, the diamonds): a part of the inventory is made one rarity better for cash and premium coins, at once and with no draw. The
 * affixes it gets are the ones that any other way to that rarity would give it (the seed is made of the part and the rarity), so the result is known before it is paid, and the
 * driver is shown it. What a step costs is a multiple of the price of the model in cash and a number of coins that grows with the tier; both are the rules', and both are taken
 * in one transaction under the lock of the wallet, once for a request id.
 *
 * Nothing but the rarity and the affixes of the part changes: its model, its level of upgrade, its wear and what the driver tuned on it (`tuning`) stay. The part is marked, and a
 * marked part is not used to merge (`transmute.merge_input`): with it the coins would buy epic parts that merging turns into legendary ones.
 */
final class PartTransmutations
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Rules $rules,
        private readonly Catalog $catalog,
        private readonly PartAffixes $affixes,
        private readonly Premium $premium,
    ) {}

    /**
     * What transmuting each part of the inventory would be: the rarity it becomes and what it costs, and why it cannot be done now (`busy`, `level` or `limit`) or null when
     * it can. A part that cannot be transmuted at all (the best rarity of the rules, a model with no price, a game with no transmutation) has null.
     *
     * @param  Collection<int, Part>  $inventory  the parts of the driver that are on no car
     * @param  list<int>  $busy  the ids of the parts that wait in a job
     * @return array<int, array{to: string, coins: int, cash_cents: int, blocked: string|null}|null>
     */
    public function quotes(int $userId, Collection $inventory, array $busy): array
    {
        $quotes = [];

        foreach ($inventory as $part) {
            $quotes[$part->id] = null;
        }

        if (! $this->rules->transmuteOn()) {
            return $quotes;
        }

        $level = $this->rules->playerLevelAt($this->ledger->xp($userId));
        $today = $this->today($userId);

        foreach ($inventory as $part) {
            $step = $this->stepOf($part);
            $definition = $this->catalog->part($part->part_slug);

            if ($step === null || ($definition['price_cents'] ?? null) === null) {
                continue;
            }

            $cost = $this->rules->transmuteCost($step, (int) $definition['tier'], (int) $definition['price_cents']);

            $quotes[$part->id] = [
                'to' => Rarity::from($part->rarity + 1)->key(),
                'coins' => $cost['coins'],
                'cash_cents' => $cost['cash_cents'],
                'blocked' => in_array($part->id, $busy, true) ? 'busy' : ($level < $this->rules->transmute()['min_player_level'] ? 'level' : ($today >= $this->rules->transmute()['limit_per_day'] ? 'limit' : null)),
            ];
        }

        return $quotes;
    }

    /**
     * What the part would be after it: the part as the client shows one, with the rarity and the affixes that it would have. Nothing is changed.
     *
     * @return array<string, mixed>
     *
     * @throws RuleRefusedException `transmute_unavailable`, `part_not_found` or `transmute_max`
     */
    public function preview(int $userId, int $partId): array
    {
        $this->ensureOn();
        $part = Part::query()->where('user_id', $userId)->find($partId) ?? throw new RuleRefusedException('part_not_found');
        $step = $this->stepOf($part) ?? throw new RuleRefusedException('transmute_max');
        $definition = $this->catalog->part($part->part_slug);

        if (($definition['price_cents'] ?? null) === null) {
            throw new RuleRefusedException('transmute_unavailable');
        }

        $cost = $this->rules->transmuteCost($step, (int) $definition['tier'], (int) $definition['price_cents']);
        $to = Rarity::from($part->rarity + 1);
        $after = $part->replicate();
        $after->id = $part->id;
        $after->rarity = $to->value;
        $after->affixes = $this->rolled($part, $to);

        return ['to' => $to->key(), 'coins' => $cost['coins'], 'cash_cents' => $cost['cash_cents'], 'after' => PartResource::make($after)->resolve()];
    }

    /**
     * Transmutes a part: pays the coins and the cash, makes it one rarity better with the affixes of it, and writes it down. Sent again with the same `$requestId` it does and
     * charges nothing (`repeated`).
     *
     * @return array{repeated: bool, coins: int, cash_cents: int, part: Part}
     *
     * @throws RuleRefusedException `transmute_unavailable`, `part_not_found`, `part_mounted`, `part_busy`, `transmute_level`, `transmute_max`, `transmute_limit`, `insufficient_premium` or `insufficient_funds` (nothing is changed)
     */
    public function transmute(int $userId, int $partId, string $requestId): array
    {
        $this->ensureOn();

        if ($requestId === '' || strlen($requestId) > RunService::MAX_REQUEST_ID_LENGTH) {
            throw new InvalidArgumentException('A transmutation needs a request id of at most '.RunService::MAX_REQUEST_ID_LENGTH.' characters.');
        }

        $this->ledger->ensureWallet($userId);

        return DB::transaction(function () use ($userId, $partId, $requestId): array {
            $this->ledger->lockWallet($userId);

            $done = PartTransmutation::query()->where('user_id', $userId)->where('request_id', $requestId)->first();

            if ($done !== null) {
                return ['repeated' => true, 'coins' => 0, 'cash_cents' => 0, 'part' => Part::query()->findOrFail($done->part_id)];
            }

            $part = Part::query()->where('user_id', $userId)->lockForUpdate()->find($partId) ?? throw new RuleRefusedException('part_not_found');

            if ($part->isMounted()) {
                throw new RuleRefusedException('part_mounted');
            }

            if (WorkshopJob::query()->where('status', '!=', WorkshopJob::STATUS_DONE)->where(fn ($query) => $query->where('part_id', $part->id)->orWhere('merge_part_id', $part->id))->exists()) {
                throw new RuleRefusedException('part_busy');
            }

            $settings = $this->rules->transmute();
            $level = $this->rules->playerLevelAt($this->ledger->xp($userId));

            if ($level < $settings['min_player_level']) {
                throw new RuleRefusedException('transmute_level', ['required' => $settings['min_player_level'], 'current' => $level]);
            }

            $step = $this->stepOf($part) ?? throw new RuleRefusedException('transmute_max');
            $definition = $this->catalog->part($part->part_slug);

            if (($definition['price_cents'] ?? null) === null) {
                throw new RuleRefusedException('transmute_unavailable');
            }

            if ($this->today($userId) >= $settings['limit_per_day']) {
                throw new RuleRefusedException('transmute_limit', ['limit' => $settings['limit_per_day']]);
            }

            $cost = $this->rules->transmuteCost($step, (int) $definition['tier'], (int) $definition['price_cents']);
            $from = Rarity::from($part->rarity);
            $to = Rarity::from($part->rarity + 1);

            if ($cost['coins'] > 0) {
                $this->premium->spend($userId, $cost['coins'], 'transmute', "transmute:{$userId}:{$requestId}");
            }

            if ($cost['cash_cents'] > 0) {
                $this->ledger->post($userId, -$cost['cash_cents'], 'transmute', "transmute-cash:{$userId}:{$requestId}");
            }

            $part->forceFill(['rarity' => $to->value, 'transmuted_at' => now()])->save();
            $this->affixes->give($part, $to);

            PartTransmutation::query()->create([
                'user_id' => $userId,
                'part_id' => $part->id,
                'part_slug' => $part->part_slug,
                'from_rarity' => $from->value,
                'to_rarity' => $to->value,
                'coins' => $cost['coins'],
                'cash_cents' => $cost['cash_cents'],
                'request_id' => $requestId,
            ]);

            return ['repeated' => false, 'coins' => $cost['coins'], 'cash_cents' => $cost['cash_cents'], 'part' => $part->refresh()];
        }, Ledger::DEADLOCK_ATTEMPTS);
    }

    /** The step a part is at (0 for common to uncommon, 1 for uncommon to rare, 2 for rare to epic), or null when it is at the best rarity that is made. */
    private function stepOf(Part $part): ?int
    {
        $best = Rarity::fromKey($this->rules->transmute()['max_rarity']);

        return $part->rarity >= $best->value || Rarity::tryFrom((int) $part->rarity) === null ? null : $part->rarity - 1;
    }

    /**
     * The affixes the part would have at the rarity: what it has stays, and what it lacks is drawn from the seed of the part and the rarity.
     *
     * @return array<string, int>
     */
    private function rolled(Part $part, Rarity $to): array
    {
        return (new Affixes($this->rules->affixes()))->roll($this->affixes->seed($part, $to), $part->slot, $to, (array) ($part->affixes ?? []));
    }

    /** How many parts the driver transmuted in the last 24 hours. */
    private function today(int $userId): int
    {
        return PartTransmutation::query()->where('user_id', $userId)->where('created_at', '>=', now()->subDay())->count();
    }

    private function ensureOn(): void
    {
        if (! $this->rules->transmuteOn()) {
            throw new RuleRefusedException('transmute_unavailable');
        }
    }
}
