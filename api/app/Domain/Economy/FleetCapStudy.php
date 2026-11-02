<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * What the limit of active cars does to the pace of the three kinds of player of the simulator, and what the slots of the VIP account are worth to them. The simulator
 * knows a player by how many cars drive at once at each rung of the ladder (`players.*.fleet` of the anchors), so the limit is played as a ceiling on that number:
 *
 *  - the baseline is the player as the anchors have them (nothing limits them);
 *  - a free player drives at most `base` cars, which for the fleets of the anchors (1.7 at most) changes nothing, and the study says so with the days;
 *  - a player with the VIP account drives as many cars as the slots allow, if they have the money for them: the part of the base slots that the player fills (1.7 of 2) is
 *    the part of all the slots they fill (4.25 of 5), from the rung at which the player has more than one car, and the account pays its bonus of cash on every lap (the
 *    activity of the player is that much more).
 *
 * This is a scenario, not a measurement of players: it tells what the numbers of the rules make of the three kinds of player, so that a change of `slots.vip` or of the
 * bonus can be seen before it is published. The days are those of the simulator of the anchors (cash and the level of the driver both have to be there for a car).
 */
final class FleetCapStudy
{
    /** The rung from which a player is taken to have the cars that the slots of the VIP account let them have (below it they have one car, which the account does not change). */
    private const FIRST_FLEET_RUNG = 2;

    public function __construct(private readonly Simulator $simulator) {}

    /**
     * @param  array<string, mixed>  $data  the anchors (content/rules/economy.json)
     * @param  int  $base  the cars every driver can have on the road
     * @param  int  $vip  the cars more that the VIP account lets a driver have
     * @param  int  $vipBonusPpm  what the VIP account adds to the cash of a lap, in ppm (200 000 is a fifth)
     * @param  list<string>  $only  the kinds of players to play, all of them when empty
     * @return array<string, array{
     *     name: string,
     *     rows: list<array{rung: string, baseline_day: int|null, free_day: int|null, vip_day: int|null}>,
     *     free_deviation: float,
     *     free_income: float,
     *     vip_income: float,
     *     ratio: float,
     *     fleet_free: float,
     *     fleet_vip: float
     * }>
     */
    public function run(array $data, int $base, int $vip, int $vipBonusPpm, array $only = []): array
    {
        $baseline = EconomyRules::fromArray($data);
        $free = EconomyRules::fromArray($this->limited($data, static fn (float $fleet, int $rung): float => min($fleet, (float) $base), 1.0));
        $withVip = EconomyRules::fromArray($this->limited($data, static fn (float $fleet, int $rung): float => $rung < self::FIRST_FLEET_RUNG ? $fleet : min((float) ($base + $vip), $fleet * ($base + $vip) / max(1, $base)), 1 + $vipBonusPpm / 1_000_000));

        $report = [];

        foreach ($baseline->players() as $key => $player) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            $baselineRun = $this->simulator->simulate($baseline, $player);
            $freeRun = $this->simulator->simulate($free, $free->players()[$key]);
            $vipRun = $this->simulator->simulate($withVip, $withVip->players()[$key]);
            $last = $baseline->rungs() - 1;
            $rows = [];
            $deviation = 0.0;

            for ($rung = 1; $rung <= $last; $rung++) {
                $was = $baselineRun->dayOf($rung);
                $now = $freeRun->dayOf($rung);

                if ($was !== null && $now !== null && $was > 0) {
                    $deviation = max($deviation, abs($now - $was) / $was);
                }

                $rows[] = ['rung' => $baseline->rungName($rung), 'baseline_day' => $was, 'free_day' => $now, 'vip_day' => $vipRun->dayOf($rung)];
            }

            $freeIncome = $free->dailyIncome($free->players()[$key], $last);
            $vipIncome = $withVip->dailyIncome($withVip->players()[$key], $last);

            $report[$key] = [
                'name' => $player->name,
                'rows' => $rows,
                'free_deviation' => $deviation,
                'free_income' => $freeIncome,
                'vip_income' => $vipIncome,
                'ratio' => $freeIncome > 0 ? $vipIncome / $freeIncome : 0.0,
                'fleet_free' => $free->players()[$key]->fleetAt($last),
                'fleet_vip' => $withVip->players()[$key]->fleetAt($last),
            ];
        }

        return $report;
    }

    /**
     * The anchors with the fleet of every player put through a function (of the number of cars and the rung) and its activity multiplied.
     *
     * @param  array<string, mixed>  $data
     * @param  callable(float, int): float  $fleetOf
     * @return array<string, mixed>
     */
    private function limited(array $data, callable $fleetOf, float $activityFactor): array
    {
        foreach ($data['players'] as $key => $player) {
            $data['players'][$key]['fleet'] = array_map(static fn (mixed $fleet, int $rung): float => $fleetOf((float) $fleet, $rung), array_values($player['fleet']), array_keys(array_values($player['fleet'])));
            $data['players'][$key]['activity'] = $player['activity'] * $activityFactor;
        }

        return $data;
    }
}
