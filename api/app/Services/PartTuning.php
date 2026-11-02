<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Catalog;
use App\Content\Rules;
use App\Domain\Market\Tuning;
use App\Domain\Workshop\WorkshopGear;
use App\Exceptions\RuleRefusedException;
use App\Models\Part;
use App\Models\WorkshopJob;
use App\Support\I18nText;
use Illuminate\Support\Collection;

/**
 * Tuning a part: a job of the workshop that makes one try at one place of the part, and puts the bonus it draws there (the `tuning` of the rules, see `Tuning`). The
 * job is paid when it is queued, waits for the mechanic like the rest, and the draw is made when he is done, on the server, from a seed that the driver cannot
 * see: the result is not known to anyone before the job ends, so it cannot be searched for. The car stands for it only when the part is on it.
 *
 * A try with no bonus picked puts one drawn by weight in an empty place, and tries again at the bonus that an occupied place holds; both cost the smaller price
 * (`tuning.cost_random_ppm` of the price of the model). Picking a bonus costs the larger one (`cost_pick_ppm`), unless it is the one the place holds.
 */
final class PartTuning
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Rules $rules,
        private readonly Catalog $catalog,
    ) {}

    /** How many places for bonuses the part has: what its model says, else what the rules say. */
    public function slotsOf(Part $part): int
    {
        return $this->engine()->slotsFor($this->catalog->part($part->part_slug)['tuning_slots'] ?? null);
    }

    /**
     * What the places of the part hold, as many entries as it has places: a bonus (`key`, `ppm`, `tries`, `fails`) or null for an empty place.
     *
     * @return list<array{key: string, ppm: int, tries: int, fails: int}|null>
     */
    public function placesOf(Part $part): array
    {
        $held = array_values((array) ($part->tuning ?? []));
        $places = [];

        for ($place = 0; $place < $this->slotsOf($part); $place++) {
            $places[] = $held[$place] ?? null;
        }

        return $places;
    }

    /**
     * What a try at a place costs and takes. The smaller price is for a draw (`$key` null) and for another try at the bonus the place holds; the larger one for putting
     * another bonus there. The time is the one of putting the part on, cut by `tuning.time_ppm_of_mount`.
     *
     * @return array{cost_cents: int, duration_ms: int}
     */
    public function quote(Part $part, int $place, ?string $key, WorkshopGear $gear): array
    {
        $definition = $this->catalog->part($part->part_slug) ?? throw new RuleRefusedException('tuning_unavailable');
        $held = $this->placesOf($part)[$place - 1] ?? null;

        return [
            'cost_cents' => $this->rules->tuningCostCents((int) ($definition['price_cents'] ?? 0), $key !== null && $key !== ($held['key'] ?? null)),
            'duration_ms' => $this->rules->tuningJobMs($part->slot, (int) $definition['tier'], $gear),
        ];
    }

    /**
     * What the part offers for the garage: why the driver cannot tune it (`locked`: the model has no price or no places, or the driver is not of the level yet), the two
     * prices and the time, and the bonuses that fit its slot at the level of the driver.
     *
     * @return array{locked: array{reason: string, required?: int, current?: int}|null, cost_random_cents: int|null, cost_pick_cents: int|null, duration_ms: int|null, bonuses: list<array<string, mixed>>}
     */
    public function offer(Part $part, int $level, WorkshopGear $gear): array
    {
        $definition = $this->catalog->part($part->part_slug);
        $price = $definition['price_cents'] ?? null;
        $closed = ['cost_random_cents' => null, 'cost_pick_cents' => null, 'duration_ms' => null, 'bonuses' => []];

        if ($definition === null || $price === null || $this->slotsOf($part) === 0) {
            return ['locked' => ['reason' => 'unavailable'], ...$closed];
        }

        if ($level < $this->rules->tuningUnlockLevel()) {
            return ['locked' => ['reason' => 'level', 'required' => $this->rules->tuningUnlockLevel(), 'current' => $level], ...$closed];
        }

        $bonuses = [];

        foreach ($this->engine()->pool($part->slot, $level) as $key => $bonus) {
            $bonuses[] = [
                'key' => $key,
                'name' => I18nText::from($bonus['name'])->for(),
                'description' => I18nText::from($bonus['description'])->for(),
                'effect' => $bonus['effect'],
                'stat' => $bonus['stat'] ?? null,
                'context' => [
                    'kinds' => $bonus['context']['kinds'] ?? null,
                    'surfaces' => $bonus['context']['surfaces'] ?? null,
                    'weathers' => $bonus['context']['weathers'] ?? null,
                ],
                // What a try gives on average, and the most it can give.
                'mean_percent' => round($bonus['mean_ppm'] / 10_000, 1),
                'max_percent' => round($bonus['max_ppm'] / 10_000, 1),
            ];
        }

        return [
            'locked' => null,
            'cost_random_cents' => $this->rules->tuningCostCents((int) $price, false),
            'cost_pick_cents' => $this->rules->tuningCostCents((int) $price, true),
            'duration_ms' => $this->rules->tuningJobMs($part->slot, (int) $definition['tier'], $gear),
            'bonuses' => $bonuses,
        ];
    }

    /**
     * Whether a try can be made at a place of the part now: the driver is of the level, the part has the place, no job waits at it, the bonus that is picked fits the
     * slot and is not at another place of the part (or on its way to one).
     *
     * @param  Collection<int, WorkshopJob>  $pending  the jobs of the driver that are not done
     *
     * @throws RuleRefusedException `tuning_unavailable`, `tuning_level_required`, `tuning_place_invalid`, `tuning_place_busy`, `tuning_bonus_unavailable`
     */
    public function check(Part $part, ?int $place, ?string $key, int $level, Collection $pending): void
    {
        if (($this->catalog->part($part->part_slug)['price_cents'] ?? null) === null || $this->slotsOf($part) === 0) {
            throw new RuleRefusedException('tuning_unavailable');
        }

        if ($level < $this->rules->tuningUnlockLevel()) {
            throw new RuleRefusedException('tuning_level_required', ['required' => $this->rules->tuningUnlockLevel(), 'current' => $level]);
        }

        if ($place === null || $place < 1 || $place > $this->slotsOf($part)) {
            throw new RuleRefusedException('tuning_place_invalid');
        }

        $waiting = $pending->where('kind', WorkshopJob::KIND_TUNE)->where('part_id', $part->id);

        if ($waiting->contains('tune_place', $place)) {
            throw new RuleRefusedException('tuning_place_busy');
        }

        $places = $this->placesOf($part);
        $held = $places[$place - 1] ?? null;
        // The bonuses the part has at its other places, and the ones that are being picked for them.
        $taken = [
            ...array_values(array_filter(array_map(static fn (int $at): ?string => $at === $place - 1 ? null : ($places[$at]['key'] ?? null), array_keys($places)))),
            ...$waiting->pluck('tune_key')->filter()->values()->all(),
        ];
        $pool = $this->engine()->pool($part->slot, $level, $taken);

        // A try with no pick needs a bonus to try again, or one to draw; a pick must be on offer.
        $wanted = $key ?? $held['key'] ?? null;

        if (($wanted === null && $pool === []) || ($wanted !== null && ! isset($pool[$wanted]))) {
            throw new RuleRefusedException('tuning_bonus_unavailable');
        }
    }

    /**
     * Makes the try of a job that is done and writes what the place holds after it. A bonus that can no longer be put there (the pack changed, or the driver
     * took it for another place in the meantime) leaves the part as it was and gives the price back. The locks of the wallet and the runs are held.
     */
    public function resolve(WorkshopJob $job): void
    {
        $part = Part::query()->lockForUpdate()->findOrFail($job->part_id);
        $places = $this->placesOf($part);
        $index = (int) $job->tune_place - 1;

        if (! array_key_exists($index, $places)) {
            $this->refund($job);

            return;
        }

        $held = $places[$index];
        $taken = array_values(array_filter(array_map(static fn (int $at): ?string => $at === $index ? null : ($places[$at]['key'] ?? null), array_keys($places))));
        $seed = Tuning::seed((string) config('app.key'), (int) $job->user_id, $part->id, (int) $job->tune_place, (int) ($held['tries'] ?? 0) + 1);
        $level = $this->rules->playerLevelAt($this->ledger->xp((int) $job->user_id));
        $result = $this->engine()->attempt($held, $job->tune_key ?? $held['key'] ?? null, $seed, $part->slot, $level, $taken);

        if ($result === null) {
            $this->refund($job);

            return;
        }

        $places[$index] = $result;
        $part->forceFill(['tuning' => $places])->save();
    }

    private function refund(WorkshopJob $job): void
    {
        if ($job->cost_cents > 0) {
            $this->ledger->post((int) $job->user_id, $job->cost_cents, 'tune_refund', "tune-refund:{$job->id}");
        }
    }

    private function engine(): Tuning
    {
        return new Tuning($this->rules->tuning());
    }
}
