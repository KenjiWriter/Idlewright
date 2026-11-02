<?php

declare(strict_types=1);

namespace App\Content;

use App\Domain\Economy\GigEconomy;

/**
 * The gigs of the game as the economy simulator sees them: the document of the gigs, the roads and the cities of the rules, and the car that the ladder gives each level
 * (the best pair of car and track, which is what a player drives), so that the study knows what that car can take.
 */
final class GameGigs
{
    public function __construct(
        private readonly Rules $rules,
        private readonly GigCatalog $catalog,
        private readonly Catalog $vehicles,
        private readonly RunInputsFactory $inputs,
    ) {}

    /**
     * Null when the game has no gigs, or switches them off.
     *
     * @param  array<int, array{net: float, car: string, track: string, xp: float}>  $incomeByLevel  the best car of every level (`GameIncome::byLevel`)
     */
    public function economy(array $incomeByLevel): ?GigEconomy
    {
        if (! $this->catalog->enabled() || $incomeByLevel === []) {
            return null;
        }

        $cars = [];

        foreach ($incomeByLevel as $level => $income) {
            $car = $this->vehicles->vehicle($income['car']) ?? [];
            $speed = min($this->inputs->stats($income['car'])->get('top_speed') * $this->rules->travelSpeedSharePpm() / 1_000_000, (float) $this->catalog->speedCapKmh());

            $cars[$level] = [
                'seats' => (int) ($car['seats'] ?? 0),
                'cargo_kg' => (int) ($car['cargo_kg'] ?? 0),
                'class' => (string) ($car['class'] ?? ''),
                'level' => (int) ($car['min_player_level'] ?? 1),
                'speed_kmh' => $speed,
            ];
        }

        $cities = [];

        foreach (array_keys($this->rules->regions()) as $slug) {
            $cities[$slug] = $this->rules->cityMinPlayerLevel($slug);
        }

        $document = $this->catalog->toArray();

        return new GigEconomy(
            bands: $document['bands'],
            anchors: $document['pay']['anchors'],
            templates: $document['templates'],
            rarities: $document['rarities'],
            cars: $cars,
            cities: $cities,
            roads: $this->rules->roads(),
            refSpeedKmh: $this->catalog->refSpeedKmh(),
            xpPerHour: (float) $this->catalog->xpPerHour(),
            fuelMlPerKm: (float) $this->rules->travelFuelMlPerKm(),
            fuelCentsPerLiter: (float) $this->rules->fuelPriceCentsPerLiter(),
        );
    }
}
