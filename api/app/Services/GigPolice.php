<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\GigCatalog;
use App\Domain\Gigs\NightOdds;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\RuleRefusedException;
use App\Models\Gig;
use Illuminate\Support\Facades\DB;

/**
 * The police on a night run. Whether a night run is stopped, and whether the car gets away or the driver is arrested, is drawn when the gig is taken, from the server's secret
 * and the run, so it does not depend on when the driver looks (`outcome`); the chance that the driver is shown before they take it (`odds`) is the one the draw is made with.
 * The body class of the car changes it (a quiet car draws less attention), and so does the heat of an escape in the same city.
 *
 * The arrest is not a state of the account but of the gig: the driver is arrested from the moment of the stop until `arrest_until_ms`, or until they pay the bail, and while they
 * are, they can take no gig and start no journey (`arrest`). Nothing runs in the background and nothing has to: the arrest is read from the gigs when anything asks.
 */
final class GigPolice
{
    public function __construct(
        private readonly GigCatalog $catalog,
        private readonly Ledger $ledger,
    ) {}

    /**
     * The chances of a night run for a car of a body class, in parts per million: what the patrols alone would stop, what the class of the car changes, the heat of an escape,
     * the chance that the run is stopped, and how it ends. Null when the game has no night run.
     *
     * @return array{patrol_ppm: int, class_ppm: int, heat_ppm: int, control_ppm: int, escape_share_ppm: int, clean_ppm: int, escaped_ppm: int, arrested_ppm: int, noise: string}|null
     */
    public function odds(string $class, bool $heated): ?array
    {
        $night = $this->catalog->night();

        return $night === null ? null : NightOdds::of($night, $class, $heated);
    }

    /** Whether the patrols of a city look harder for the driver, because a car of theirs got away from them there not long ago. */
    public function heated(int $userId, string $region, int $nowMs): bool
    {
        $night = $this->catalog->night();

        if ($night === null || $night['risk']['heat_ms'] <= 0 || $night['risk']['heat_ppm'] <= 0) {
            return false;
        }

        return Gig::query()
            ->where('user_id', $userId)
            ->where('region', $region)
            ->where('police_outcome', Gig::POLICE_ESCAPED)
            ->where('status', '!=', Gig::STATUS_CANCELLED)
            ->where('police_at_ms', '<=', $nowMs)
            ->where('police_at_ms', '>', $nowMs - $night['risk']['heat_ms'])
            ->exists();
    }

    /**
     * How a night run ends: nobody stops it, the car gets away, or the driver is arrested. Drawn from the secret of the server, the run, the moment of the start and the offer,
     * so that nobody can know it before the gig is taken and nobody can change it after.
     *
     * @param  array{control_ppm: int, escape_share_ppm: int}  $odds
     */
    public function outcome(array $odds, int $runId, int $startMs, string $offerKey): string
    {
        if ($this->draw('control', $runId, $startMs, $offerKey) >= $odds['control_ppm']) {
            return Gig::POLICE_CLEAN;
        }

        return $this->draw('escape', $runId, $startMs, $offerKey) < $odds['escape_share_ppm'] ? Gig::POLICE_ESCAPED : Gig::POLICE_ARRESTED;
    }

    /**
     * The arrest of a driver at this moment, or null. It began at the stop (which is in the middle of the trip), so a driver who comes back after hours finds it over.
     *
     * @return array{gig: int, since_ms: int, until_ms: int, bail_cents: int}|null
     */
    public function arrest(int $userId, int $nowMs): ?array
    {
        $gig = $this->arrestOf($userId, $nowMs);

        return $gig === null ? null : ['gig' => $gig->id, 'since_ms' => (int) $gig->police_at_ms, 'until_ms' => (int) $gig->arrest_until_ms, 'bail_cents' => (int) $gig->bail_cents];
    }

    /** When the arrest of a driver ends, or null when they are not under arrest. */
    public function arrestedUntilMs(int $userId, int $nowMs): ?int
    {
        return $this->arrestOf($userId, $nowMs)?->arrest_until_ms;
    }

    /**
     * Pays the bail: the arrest ends at once, and the bail is taken from the cash of the driver.
     *
     * @throws RuleRefusedException `not_arrested` (the driver is not under arrest, or it is over)
     * @throws InsufficientFundsException the driver cannot pay the bail (nothing is changed)
     */
    public function bail(int $userId, ?int $nowMs = null): void
    {
        // Before the transaction: creating the wallet under a lock deadlocks (see Ledger::ensureWallet).
        $this->ledger->ensureWallet($userId);

        DB::transaction(function () use ($userId, $nowMs): void {
            $this->ledger->lockWallet($userId);
            $now = $nowMs ?? RunService::nowMs();
            $gig = $this->arrestOf($userId, $now, lock: true) ?? throw new RuleRefusedException('not_arrested');
            $bail = (int) $gig->bail_cents;
            $cash = $this->ledger->balance($userId);

            if ($cash < $bail) {
                throw new InsufficientFundsException($cash, $bail);
            }

            if ($bail > 0) {
                $this->ledger->post($userId, -$bail, 'gig_bail', "gig:{$gig->id}:bail");
            }

            $gig->forceFill(['bailed_ms' => $now])->save();
        }, Ledger::DEADLOCK_ATTEMPTS);
    }

    private function arrestOf(int $userId, int $nowMs, bool $lock = false): ?Gig
    {
        $query = Gig::query()
            ->where('user_id', $userId)
            ->where('police_outcome', Gig::POLICE_ARRESTED)
            ->where('status', '!=', Gig::STATUS_CANCELLED)
            ->whereNull('bailed_ms')
            ->where('police_at_ms', '<=', $nowMs)
            ->where('arrest_until_ms', '>', $nowMs)
            ->orderByDesc('arrest_until_ms');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    /** A number from 0 to 999 999 that nobody can know before the gig is taken and nobody can change after. */
    private function draw(string $label, int $runId, int $startMs, string $offerKey): int
    {
        return (int) (hexdec(substr(hash_hmac('sha256', "gig-police|{$label}|{$runId}|{$startMs}|{$offerKey}", (string) config('app.key')), 0, 8)) % 1_000_000);
    }
}
