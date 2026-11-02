<?php

declare(strict_types=1);

namespace App\Domain\Market;

use App\Domain\Run\Rng;

/**
 * The bonuses that a player puts on a part (the `tuning` of the rules), as against the affixes that a part is drawn with once. Pure: the same seed gives the same
 * result on every machine, and the seed is made by the server from a secret, so nobody can know a result before the try is made.
 *
 * A part has places for bonuses; each is empty or holds `{key, ppm, tries, fails}`: the bonus, its amount, how many tries were made at the place, and how many of them
 * in a row did not make it better. A try is paid for and draws an amount from a bell curve: the sum of twelve even draws (all whole numbers, so nothing that is
 * counted ever sees a float) minus its mean has a spread of exactly one, so `mean + sigma × z` is as good as a normal draw to three sigma, and it stops at six. The
 * amount is cut between the least and the most of its bonus, so the higher it is, the rarer it is.
 *
 * A try at the bonus that the place holds keeps the better of the two amounts, so it never costs the player what they have; a try that puts another bonus in the
 * place replaces it, and its first amount is not lower than `floor_sigma_ppm` of sigma below the mean, so changing for another track is not a punishment.
 */
final class Tuning
{
    /** How many even draws make the bell. */
    private const DRAWS = 12;

    /** The mean of the sum of the draws: each is from 0 to 999 999, so a draw is 499 999.5 on average. */
    private const CENTRE = 5_999_994;

    /** n over k for n = 12, for the exact tail that the panel shows. */
    private const CHOOSE = [1, 12, 66, 220, 495, 792, 924, 792, 495, 220, 66, 12, 1];

    /** 12!, the divisor of the exact distribution of the sum of twelve even draws. */
    private const FACTORIAL = 479_001_600.0;

    /** @param  array<string, mixed>  $config  `tuning` of the rules */
    public function __construct(private readonly array $config) {}

    /** The seed of the try number `$try` at a place of a part: from the secret of the application, the driver, the part and the place. */
    public static function seed(string $secret, int $userId, int $partId, int $place, int $try): string
    {
        return hash_hmac('sha256', "tuning|{$userId}|{$partId}|{$place}|{$try}", $secret);
    }

    /**
     * A draw from the bell curve for a bonus, in millionths of sigma: the sum of twelve even draws less its mean. Whole numbers from -6 000 000 to +6 000 000.
     */
    public static function z(string $seed, string $key, int $try): int
    {
        $sum = 0;

        for ($draw = 1; $draw <= self::DRAWS; $draw++) {
            $sum += Rng::roll($seed, "tuning-gauss:{$key}:{$try}", $draw);
        }

        return $sum - self::CENTRE;
    }

    /**
     * The amount that a draw gives for a bonus, in ppm: `mean + sigma × z`, rounded half away from zero, cut between the least and the most of the bonus.
     *
     * @param  array<string, mixed>  $bonus  one of `definitions`
     * @param  int|null  $floor  the lowest z that counts, in millionths of sigma (below it the draw is lifted to it), or null for no floor
     */
    public static function amount(array $bonus, int $z, ?int $floor = null): int
    {
        if ($floor !== null) {
            $z = max($z, -$floor);
        }

        $product = $bonus['sigma_ppm'] * $z;
        $spread = intdiv($product + ($product >= 0 ? 500_000 : -500_000), 1_000_000);

        return max($bonus['min_ppm'], min($bonus['max_ppm'], $bonus['mean_ppm'] + $spread));
    }

    /** How many places a part has, when its model says `$modelSlots` (null: the rules decide). */
    public function slotsFor(?int $modelSlots): int
    {
        return max(0, min(3, $modelSlots ?? (int) ($this->config['slots'] ?? 0)));
    }

    /**
     * The bonuses that can be put in a place of a slot by a driver of the level: the ones that fit the slot and are on offer at the level, without the ones that the
     * part has at its other places (a part never has the same bonus twice).
     *
     * @param  list<string>  $taken  the keys of the bonuses at the other places of the part
     * @return array<string, array<string, mixed>>
     */
    public function pool(string $slot, int $level, array $taken = []): array
    {
        return array_filter(
            (array) ($this->config['definitions'] ?? []),
            static fn (array $bonus, string $key): bool => ! in_array($key, $taken, true) && $level >= $bonus['from_level'] && in_array($slot, $bonus['slots'], true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The bonus that a try with no pick puts in the place: one of the pool, by weight. Null when the pool is empty.
     *
     * @param  array<string, array<string, mixed>>  $pool
     */
    public function pick(string $seed, array $pool, int $try): ?string
    {
        if ($pool === []) {
            return null;
        }

        $pick = intdiv(Rng::roll($seed, 'tuning-pick', $try) * array_sum(array_column($pool, 'weight')), 1_000_000);

        foreach ($pool as $key => $bonus) {
            if ($pick < $bonus['weight']) {
                return $key;
            }

            $pick -= $bonus['weight'];
        }

        return array_key_last($pool);
    }

    /**
     * One try at a place: what the place holds after it. `$key` is the bonus the driver picked, or null for one drawn by weight. Null when no bonus can be put there
     * (the pick does not fit the slot, is not on offer yet, or is at another place of the part; or the pool is empty).
     *
     * @param  array{key: string, ppm: int, tries: int, fails: int}|null  $held  what the place holds now
     * @param  list<string>  $taken  the keys of the bonuses at the other places of the part
     * @return array{key: string, ppm: int, tries: int, fails: int}|null
     */
    public function attempt(?array $held, ?string $key, string $seed, string $slot, int $level, array $taken = []): ?array
    {
        $pool = $this->pool($slot, $level, $taken);
        $tries = (int) ($held['tries'] ?? 0) + 1;
        $key ??= $this->pick($seed, $pool, $tries);

        if ($key === null || ! isset($pool[$key])) {
            return null;
        }

        $same = ($held['key'] ?? null) === $key;
        $fails = $same ? (int) ($held['fails'] ?? 0) : 0;
        $drawn = self::amount($pool[$key], self::z($seed, $key, $tries) + $this->pity($fails), $same ? null : (int) ($this->config['floor_sigma_ppm'] ?? 0));
        $improved = ! $same || $drawn > (int) $held['ppm'];

        return ['key' => $key, 'ppm' => $same ? max($drawn, (int) $held['ppm']) : $drawn, 'tries' => $tries, 'fails' => $improved ? 0 : $fails + 1];
    }

    /** What the protection from bad luck moves the mean by after this many tries that did not help, in millionths of sigma. */
    public function pity(int $fails): int
    {
        return min((int) ($this->config['pity_max_ppm'] ?? 0), (int) ($this->config['pity_step_ppm'] ?? 0) * $fails);
    }

    /** The most a bonus may give at its best by the rules: one with no context works on every track, so it may give less. */
    public function limit(array $bonus): int
    {
        return (int) ($this->config[empty($bonus['context']) ? 'max_ppm_plain' : 'max_ppm_context'] ?? 1_000_000);
    }

    /**
     * How likely a try is to give at least `$ppm` (a number for the panel, exact, not a draw): 1 at or below the least, 0 above the most.
     *
     * @param  array<string, mixed>  $bonus
     * @param  int  $shift  what the protection from bad luck has moved the mean by, in millionths of sigma
     */
    public static function chanceAtLeast(array $bonus, int $ppm, int $shift = 0): float
    {
        if ($ppm <= $bonus['min_ppm']) {
            return 1.0;
        }

        if ($ppm > $bonus['max_ppm']) {
            return 0.0;
        }

        // A draw rounds to the nearest ppm, so at least `$ppm` is at least half a ppm below it.
        $z = (($ppm - 0.5 - $bonus['mean_ppm']) / $bonus['sigma_ppm']) - $shift / 1_000_000;

        return 1.0 - self::cdf($z + 6.0);
    }

    /** How many tries it takes on average to give at least `$ppm`, or null when no try can. */
    public static function triesToReach(array $bonus, int $ppm, int $shift = 0): ?float
    {
        $chance = self::chanceAtLeast($bonus, $ppm, $shift);

        return $chance > 0.0 ? 1.0 / $chance : null;
    }

    /** The distribution function of the sum of twelve even draws from 0 to 1, at `$x` (from 0 to 12). */
    private static function cdf(float $x): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }

        if ($x >= self::DRAWS) {
            return 1.0;
        }

        $sum = 0.0;

        for ($k = 0; $k <= (int) floor($x); $k++) {
            $sum += ($k % 2 === 0 ? 1 : -1) * self::CHOOSE[$k] * (($x - $k) ** self::DRAWS);
        }

        return max(0.0, min(1.0, $sum / self::FACTORIAL));
    }
}
