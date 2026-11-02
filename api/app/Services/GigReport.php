<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Catalog;
use App\Content\GameIncome;
use App\Content\GigCatalog;
use App\Content\Rules;
use App\Content\RunInputsFactory;

/**
 * What an hour of a gig is worth against an hour of laps, for every car of the pack and every template that the car can take: the measure of the rule of the design that a
 * gig pays 60 to 90% of what the rung of the ladder of the car pays an hour (R1), and that it does not beat the laps of a car that is made for them (R2).
 *
 * A gig pays by the reference time of the trip and takes the time the car needs, so the pay of an hour does not depend on how long the trip is: it is the pay of an hour of the
 * level, times the multipliers of the template, times how much faster than the reference speed the car drives (not above the limit of the roads), less the fuel. The laps are
 * measured by `GameIncome`, the same way the economy simulator measures them, so a change in the way a lap is made is seen here without a line being changed.
 */
final class GigReport
{
    private const HOUR_MS = 3_600_000;

    public function __construct(
        private readonly GigCatalog $gigs,
        private readonly Catalog $catalog,
        private readonly Rules $rules,
        private readonly RunInputsFactory $inputs,
        private readonly GameIncome $income,
    ) {}

    /**
     * One row for every pair of a car and a template that can be taken. Dollars and ratios; an ordinary offer in a phase that pays plain.
     *
     * @return list<array{car: string, class: string, level: int, template: string, kind: string, anchor: float, speed_kmh: float, gross_per_hour: float, net_per_hour: float, r1: float, track: string, track_net: float, r2: float}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->catalog->vehicles() as $slug => $car) {
            $level = (int) ($car['min_player_level'] ?? 1);
            $stats = $this->inputs->stats($slug);
            $speed = min($stats->get('top_speed') * $this->rules->travelSpeedSharePpm() / 1_000_000, (float) $this->gigs->speedCapKmh());
            $anchor = $this->gigs->anchorCentsPerHour($level);
            $ratio = $this->gigs->band($level)['ratio_ppm'];
            [$track, $trackNet] = $this->bestTrack($slug, $level);

            foreach ($this->gigs->templates() as $key => $template) {
                if (! $this->takes($car, $template, $level, $speed)) {
                    continue;
                }

                $gross = $anchor * $ratio / 1_000_000 * $template['pay_ppm'] / 1_000_000 * $speed / $this->gigs->refSpeedKmh();
                $fuelPerHour = $this->rules->travelFuelMlPerKm() * $stats->fuelUseMultiplier * $speed / 1000 * $this->rules->fuelPriceCentsPerLiter();
                $net = $gross - $fuelPerHour;

                $rows[] = [
                    'car' => $slug,
                    'class' => (string) ($car['class'] ?? ''),
                    'level' => $level,
                    'template' => $key,
                    'kind' => $template['kind'],
                    'anchor' => $anchor / 100,
                    'speed_kmh' => round($speed, 1),
                    'gross_per_hour' => round($gross / 100, 2),
                    'net_per_hour' => round($net / 100, 2),
                    'r1' => round($net / $anchor, 3),
                    'track' => $track,
                    'track_net' => round($trackNet, 2),
                    'r2' => $trackNet > 0 ? round($net / 100 / $trackNet, 3) : 0.0,
                ];
            }
        }

        return $rows;
    }

    /**
     * The rows that are out of what the design asks: R1 outside the bounds (the pay of an hour against the rung of the ladder), or R2 too high for a car that is not made for
     * gigs (a class that no template asks for): a wreck of the yard, which anyone can buy for a few hundred dollars and fill a garage with, must not earn more on gigs than on
     * laps (R2 above 1), and a car that is bought at the dealer or on the exchange not more than `$maxR2` times, because then the gig would beat the laps of a car that is made
     * for them. The cars that gigs are made for (the classes that a template asks for) are not asked about R2: the track is not their work.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function offences(array $rows, float $minR1 = 0.5, float $maxR1 = 0.95, float $maxR2 = 1.3): array
    {
        $utility = [];

        foreach ($this->gigs->templates() as $template) {
            array_push($utility, ...($template['class_in'] ?? []));
        }

        $offences = [];

        foreach ($rows as $row) {
            if ($row['r1'] < $minR1 || $row['r1'] > $maxR1) {
                $offences[] = [...$row, 'why' => 'r1'];
            } elseif ($row['r2'] > ($this->rules->wreckPriceCents($row['car']) !== null ? 1.0 : $maxR2) && ! in_array($row['class'], $utility, true)) {
                $offences[] = [...$row, 'why' => 'r2'];
            }
        }

        return $offences;
    }

    /**
     * Whether a car can take offers of the template: it has the room, is of the class, the template is open at the level the car is sold at, and the car is quick enough for the
     * deadline of the template (the deadline is a part of the reference time, so it is the speed that decides, not the length).
     *
     * @param  array<string, mixed>  $car
     * @param  array<string, mixed>  $template
     */
    private function takes(array $car, array $template, int $level, float $speed): bool
    {
        if (($car[$template['capacity']] ?? 0) < $template['amount']['min'] || $template['min_player_level'] > $level) {
            return false;
        }

        if (isset($template['class_in']) && ! in_array($car['class'] ?? '', $template['class_in'], true)) {
            return false;
        }

        return ! isset($template['deadline_ppm']) || $this->gigs->refSpeedKmh() * 1_000_000 <= $speed * $template['deadline_ppm'];
    }

    /**
     * The track on which the car with its stock parts earns most an hour, among the tracks that the level the car is sold at opens (a wreck of the yard is bought from the
     * first level, whatever its model asks).
     *
     * @return array{0: string, 1: float} the track and the net dollars an hour on it
     */
    private function bestTrack(string $car, int $level): array
    {
        $best = ['', 0.0];

        foreach (array_keys($this->rules->regions()) as $region) {
            foreach ($this->rules->tracksOf($region) as $track) {
                if ($this->rules->trackMinPlayerLevel($track) > $level) {
                    continue;
                }

                $net = $this->income->netPerHour($car, $track);

                if ($net > $best[1]) {
                    $best = [$track, $net];
                }
            }
        }

        return $best;
    }
}
