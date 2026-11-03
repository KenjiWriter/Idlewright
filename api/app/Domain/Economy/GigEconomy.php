<?php

declare(strict_types=1);

namespace App\Domain\Economy;

use App\Domain\Gigs\NightOdds;
use App\Domain\Run\RoadMap;

/**
 * The gigs of the game as the economy simulator sees them: for a driver of a level, what a gig is worth on average (cash after the fuel, experience, hours of the
 * reference time), over the offers that the car the driver plays can take. Pure: `Content\GameGigs` makes it from the pack.
 *
 * Everything is an expected value, like the crates and the missions: an offer of the board is a template of a phase of the day, with a trip and a rarity, and the mean is
 * taken over the templates that the car can take (the room, the class, the speed for a deadline), in each phase of the day by the part of the day that it takes.
 *
 * The car is the one the ladder says earns most at the level (a racing car, with a few seats and little room): so the gigs of this study are the ones that the main car of a
 * player can take. The other cars of the garage, which can take the freight, are not counted, which keeps the study on the cautious side.
 */
final readonly class GigEconomy
{
    /** How many hours of the game each phase of the day takes (of 24): the share of the offers a driver sees in it. */
    public const PHASE_HOURS = ['dawn' => 3, 'day' => 10, 'dusk' => 4, 'night' => 7];

    /**
     * @param  list<array{from_level: int, ratio_ppm: int, max_ref_minutes: int, daily_limit: int}>  $bands
     * @param  list<array{from_level: int, cents_per_hour: int}>  $anchors
     * @param  array<string, array<string, mixed>>  $templates  as the document of the gigs has them
     * @param  list<array{key: string, weight: int, pay_ppm: int}>  $rarities
     * @param  array<int, array{seats: int, cargo_kg: int, class: string, level: int, speed_kmh: float}>  $cars  the car that the ladder gives a level, by the level
     * @param  array<string, int>  $cities  the level that opens each city
     * @param  list<array{from: string, to: string, km: int}>  $roads
     * @param  array<string, mixed>|null  $night  the night run of the document of the gigs, or null when the game has none
     */
    public function __construct(
        public array $bands,
        public array $anchors,
        public array $templates,
        public array $rarities,
        public array $cars,
        public array $cities,
        public array $roads,
        public int $refSpeedKmh,
        public float $xpPerHour,
        public float $fuelMlPerKm,
        public float $fuelCentsPerLiter,
        public ?array $night = null,
    ) {}

    /** The most gigs a driver of this level may take in a day. */
    public function dailyLimit(int $level): int
    {
        return $this->row($this->bands, $level)['daily_limit'];
    }

    /** The most night runs a driver may take in a day, or 0 when the game has none. */
    public function nightLimit(): int
    {
        return (int) ($this->night['daily_limit'] ?? 0);
    }

    /**
     * What a night run is worth on average to a driver of this level, over the ways it ends (the police stop a part of them, and the car of the level has a body class that
     * changes the part), or null when the game has none, the level is below it, or the main car cannot carry the load or reach a city.
     *
     * @return array{cash: float, xp: float, hours: float}|null cash in dollars after the fuel
     */
    public function perNightRun(int $level): ?array
    {
        if ($this->night === null) {
            return null;
        }

        $car = $this->row($this->cars, $level, 'level_key');
        $band = $this->row($this->bands, $level);

        if ($level < $this->night['min_player_level'] || $car['cargo_kg'] < $this->night['amount']['min']) {
            return null;
        }

        $km = $this->tripKm(['mode' => 'intercity'], $level, intdiv($band['max_ref_minutes'] * $this->refSpeedKmh, 60));

        if ($km === null) {
            return null;
        }

        $odds = NightOdds::of($this->night, $car['class'], false);
        $anchor = $this->row($this->anchors, $car['level'])['cents_per_hour'];
        $refHours = $km / $this->refSpeedKmh;
        $rarity = ($this->rarities[0]['pay_ppm'] ?? 1_000_000) / 1_000_000;
        $reward = $anchor * $band['ratio_ppm'] / 1_000_000 * $this->night['pay_ppm'] / 1_000_000 * $rarity * $refHours;
        $fuel = $km * $this->fuelMlPerKm / 1000 * $this->fuelCentsPerLiter;

        return [
            'cash' => ($reward * NightOdds::worth($this->night, $odds) - $fuel) / 100,
            // A driver who is arrested is not given the experience of the gig.
            'xp' => $this->xpPerHour * $refHours * (1 - $odds['arrested_ppm'] / 1_000_000),
            'hours' => $refHours,
        ];
    }

    /**
     * What a gig is worth on average to a driver of this level, over the offers that the main car can take, or null when it can take none.
     *
     * @return array{cash: float, xp: float, hours: float, share: float}|null cash in dollars after the fuel, experience, hours of the reference time, and the share of the offers that the car can take
     */
    public function perGig(int $level): ?array
    {
        $car = $this->row($this->cars, $level, 'level_key');
        $band = $this->row($this->bands, $level);
        $anchor = $this->row($this->anchors, $car['level'])['cents_per_hour'];
        $capKm = intdiv($band['max_ref_minutes'] * $this->refSpeedKmh, 60);
        $rarity = array_sum(array_map(static fn (array $row): int => $row['weight'] * $row['pay_ppm'], $this->rarities)) / max(1, array_sum(array_column($this->rarities, 'weight'))) / 1_000_000;

        $cash = 0.0;
        $xp = 0.0;
        $hours = 0.0;
        $mass = 0.0;
        $share = 0.0;

        foreach (self::PHASE_HOURS as $phase => $phaseHours) {
            $weights = [];
            $open = 0;

            foreach ($this->templates as $key => $template) {
                if ($template['phases'][$phase] <= 0 || $template['min_player_level'] > $level || $this->tripKm($template, $level, $capKm) === null) {
                    continue;
                }

                $open += $template['phases'][$phase];

                if ($this->takes($car, $template, $level, $capKm)) {
                    $weights[$key] = $template['phases'][$phase];
                }
            }

            $total = array_sum($weights);

            if ($total > 0) {
                $share += $total / $open * $phaseHours / 24;
            }

            foreach ($weights as $key => $weight) {
                $template = $this->templates[$key];
                $odds = $weight / $total * $phaseHours / 24;
                $km = (float) $this->tripKm($template, $level, $capKm);
                $refHours = $km / $this->refSpeedKmh;
                $reward = $anchor * $band['ratio_ppm'] / 1_000_000 * $template['pay_ppm'] / 1_000_000 * ($template['phase_pay_ppm'][$phase] ?? 1_000_000) / 1_000_000 * $rarity * $refHours;
                $fuel = $km * $this->fuelMlPerKm / 1000 * $this->fuelCentsPerLiter;

                $cash += $odds * ($reward - $fuel) / 100;
                $xp += $odds * $this->xpPerHour * $refHours;
                $hours += $odds * $refHours;
                $mass += $odds;
            }
        }

        // The mean is over the offers the car can take, in the part of the day in which it has one; the share is how many of the offers on the board those are.
        return $mass > 0.0 ? ['cash' => $cash / $mass, 'xp' => $xp / $mass, 'hours' => $hours / $mass, 'share' => $share / $mass] : null;
    }

    /**
     * Whether the car can take offers of the template: it has the room, is of the class, the template is open at the level, and the car is quick enough for the deadline.
     *
     * @param  array{seats: int, cargo_kg: int, class: string, level: int, speed_kmh: float}  $car
     * @param  array<string, mixed>  $template
     */
    private function takes(array $car, array $template, int $level, int $capKm): bool
    {
        if (($car[$template['capacity'] === 'seats' ? 'seats' : 'cargo_kg']) < $template['amount']['min'] || $template['min_player_level'] > $level) {
            return false;
        }

        if (isset($template['class_in']) && ! in_array($car['class'], $template['class_in'], true)) {
            return false;
        }

        return ! isset($template['deadline_ppm']) || $this->refSpeedKmh * 1_000_000 <= $car['speed_kmh'] * $template['deadline_ppm'];
    }

    /**
     * The length of a trip of the template on average, in km there and back, or null when no trip of it fits the longest gig the level allows: a trip in the city is
     * between its smallest and its biggest (not above the longest), and one to another city is the mean over the pairs of open cities that a road joins and that fit.
     *
     * @param  array<string, mixed>  $template
     */
    private function tripKm(array $template, int $level, int $capKm): ?float
    {
        if ($template['mode'] === 'local') {
            return $template['local_km']['min'] > $capKm ? null : ($template['local_km']['min'] + min($template['local_km']['max'], $capKm)) / 2;
        }

        $map = new RoadMap($this->roads);
        $open = array_keys(array_filter($this->cities, static fn (int $opens): bool => $opens <= $level));
        $trips = [];

        foreach ($open as $from) {
            foreach ($open as $to) {
                $km = $from === $to ? null : $map->distanceKm($from, $to);

                if ($km !== null && 2 * $km <= $capKm) {
                    $trips[] = 2 * $km;
                }
            }
        }

        return $trips === [] ? null : array_sum($trips) / count($trips);
    }

    /**
     * The row of a list that starts at the highest level not above this one (the first when the level is below them all).
     *
     * @param  array<int|string, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function row(array $rows, int $level, string $by = 'from_level'): array
    {
        $chosen = null;
        $chosenAt = -1;

        foreach ($rows as $index => $row) {
            $at = $by === 'level_key' ? (int) $index : $row[$by];

            if (($at <= $level && $at >= $chosenAt) || $chosen === null) {
                $chosen = $row;
                $chosenAt = $at;
            }
        }

        return $chosen;
    }
}
