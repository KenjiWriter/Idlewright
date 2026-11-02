<?php

declare(strict_types=1);

namespace App\Domain\Economy;

/**
 * What the work that the missions ask for costs, in tanks of the car of each rung, set against what the missions say it is worth (`effort`): the crate that a mission
 * asks to open, the part it asks to buy and the car it asks to buy. A tank is the profit that the car makes in the window of its rung (what the missions pay their
 * parts of), so a mission that asks for one crate and says it is worth one tank is right at the rungs where a crate costs about one tank.
 *
 * It is a report: it fails nothing, and it audits the three goals whose price the anchors give; the others (the upgrades, the services, the journeys) are priced by
 * the rules of the game from the part and the lap, and are not audited here.
 */
final class EffortAudit
{
    /** A goal is off at a rung when it costs more than twice or less than half of what the missions say it is worth. */
    public const TOLERANCE = 2.0;

    /** @var array<string, string> the goal of the missions by the work that is priced */
    private const GOALS = ['crate' => 'crate', 'part' => 'parts_bought', 'car' => 'car_bought'];

    /**
     * What each priced work costs at each rung, in tanks, and what the missions say it is worth.
     *
     * @return array{efforts: array<string, float|null>, rows: list<array{rung: string, level: int, tank: float, crate: float|null, part: float|null, car: float|null}>}
     */
    public function run(EconomyRules $rules, CrateEconomy $crates, MissionEconomy $missions): array
    {
        $rows = [];
        $last = $rules->rungs() - 1;

        for ($rung = 0; $rung <= $last; $rung++) {
            $level = $rules->minPlayerLevel($rung);
            $tier = $crates->tierAt($rules, $level);
            $tank = $rules->netPerHour($rung) * $rules->offlineWindowHours($rung);

            $rows[] = [
                'rung' => $rules->rungName($rung),
                'level' => $level,
                'tank' => $tank,
                'crate' => $tier > 0 ? $crates->cost($rules, $tier) / $tank : null,
                'part' => $tier > 0 ? $this->partPrice($rules, $crates, $tier) / $tank : null,
                'car' => $rung < $last ? $rules->stepCost($rung) / $tank : null,
            ];
        }

        return ['efforts' => array_map(static fn (string $goal): ?float => $missions->effortOf($goal), self::GOALS), 'rows' => $rows];
    }

    /**
     * The goals whose cost is off the effort at some rung, with the cost at the rungs where it is least and most off, as a ratio of the cost to the effort.
     *
     * @param  array{efforts: array<string, float|null>, rows: list<array<string, mixed>>}  $audit
     * @return array<string, array{effort: float, lowest: float, highest: float}>
     */
    public function off(array $audit): array
    {
        $off = [];

        foreach (array_keys(self::GOALS) as $work) {
            $effort = $audit['efforts'][$work] ?? null;
            $ratios = array_filter(array_map(static fn (array $row): ?float => isset($row[$work]) && $effort ? $row[$work] / $effort : null, $audit['rows']), static fn (?float $ratio): bool => $ratio !== null);

            if ($ratios !== [] && (max($ratios) > self::TOLERANCE || min($ratios) < 1 / self::TOLERANCE)) {
                $off[$work] = ['effort' => (float) $effort, 'lowest' => min($ratios), 'highest' => max($ratios)];
            }
        }

        return $off;
    }

    /** The mean price of the parts of a tier over the slots of the crates, by their mix, in dollars. */
    private function partPrice(EconomyRules $rules, CrateEconomy $crates, int $tier): float
    {
        $total = 0.0;

        foreach ($crates->slotMix as $slot => $count) {
            $total += $count * ($rules->partPrice($tier, $slot) ?? 0.0);
        }

        return $total / array_sum($crates->slotMix);
    }
}
