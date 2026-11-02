<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * Plays the extras that the balance gates deliberately do not measure, on top of the scenario game, for each kind of player: the missions (a player who does all of
 * them and one who does most of them), the VIP account, the auto-refill (a few charges a day), the pass of the endless refill, and all of them together. Each is played
 * against the same player who pays nothing, so that the table that comes of it says how much sooner a payer buys the cars of the ladder, how much more cash and
 * experience it has in the first thirty days, and what that costs it in a month.
 *
 * It is a report, not a gate: it fails nothing. Pure and deterministic, like the rest of the simulator: the numbers of the game come in as `MissionEconomy` and
 * `PerkEconomy`, and a game without missions or without paid extras simply has no row for them.
 */
final class PayerStudy
{
    /** The shares of the missions that a player completes: all of them, and most of them. */
    private const COMPLETIONS = [1.0, 0.6];

    /** The charges of the auto-refill that a player uses in a day. */
    private const CHARGES = [1.0, 3.0];

    /** The parts of what the missions pay now at which a player who does all of them is played too, to see what a lower level of them would do. */
    private const MISSION_LEVELS = [0.25, 0.5, 0.75];

    public function __construct(private readonly Simulator $simulator) {}

    /** @param  list<string>  $only  the kinds of players to play, all of them when empty */
    public function run(EconomyRules $rules, ?MissionEconomy $missions, ?PerkEconomy $perks, array $only = []): PayerResult
    {
        $variants = [];

        foreach ($this->plans($missions, $perks) as $key => $plan) {
            $variants[$key] = ['label' => $plan['label'], 'runs' => $this->keep($this->simulator->simulateAllGame($rules, $plan['extras']), $only)];
        }

        $levels = [];

        foreach ($missions === null ? [] : self::MISSION_LEVELS as $factor) {
            $levels[(string) $factor] = $this->keep($this->simulator->simulateAllGame($rules, static fn (): array => [new MissionsExtra($missions->scaled($factor), 1.0, 'missions')]), $only);
        }

        return new PayerResult($this->keep($this->simulator->simulateAllGame($rules), $only), $variants, $rules->gateTolerance(), $levels);
    }

    /**
     * What to play, by key: the label and the extras of a player.
     *
     * @return array<string, array{label: string, extras: callable(PlayerType): list<Extra>}>
     */
    private function plans(?MissionEconomy $missions, ?PerkEconomy $perks): array
    {
        $plans = [];

        if ($missions !== null) {
            foreach (self::COMPLETIONS as $completion) {
                $percent = (int) round($completion * 100);
                $plans["missions-{$percent}"] = ['label' => "missions, {$percent}% done", 'extras' => static fn (): array => [new MissionsExtra($missions, $completion, 'missions')]];
            }
        }

        if ($perks !== null && $perks->vipCashPpm + $perks->vipXpPpm > 0) {
            $plans['vip'] = ['label' => 'VIP account', 'extras' => static fn (): array => [new VipExtra($perks)]];
        }

        if ($perks !== null && $perks->coinsPerCharge > 0.0) {
            foreach (self::CHARGES as $charges) {
                $count = (int) $charges;
                $plans["refill-{$count}"] = ['label' => "auto-refill, {$count} a day", 'extras' => static fn (): array => [new RefillExtra($perks, $charges)]];
            }
        }

        if ($perks !== null && $perks->passPriceMinor > 0) {
            $plans['pass'] = ['label' => 'pass of the endless refill', 'extras' => static fn (): array => [new PassExtra($perks)]];
        }

        $everything = array_filter([
            $missions !== null ? static fn (): Extra => new MissionsExtra($missions, 1.0, 'missions') : null,
            $perks !== null && $perks->vipCashPpm + $perks->vipXpPpm > 0 ? static fn (): Extra => new VipExtra($perks) : null,
            $perks !== null && $perks->passPriceMinor > 0 ? static fn (): Extra => new PassExtra($perks) : null,
        ]);

        if (count($everything) > 1) {
            $plans['all-in'] = ['label' => 'everything: missions 100%, VIP and the pass', 'extras' => static fn (): array => array_map(static fn (callable $make): Extra => $make(), $everything)];
        }

        return $plans;
    }

    /**
     * @param  array<string, PlayerRun>  $runs
     * @param  list<string>  $only
     * @return array<string, PlayerRun>
     */
    private function keep(array $runs, array $only): array
    {
        return $only === [] ? $runs : array_intersect_key($runs, array_flip($only));
    }
}
