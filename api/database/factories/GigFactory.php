<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gig;
use App\Models\RaceRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gig>
 */
class GigFactory extends Factory
{
    /**
     * A taxi ride to the next city and back that has just begun and lasts 30 minutes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->guest(),
            'race_run_id' => fn (array $attributes): int => RaceRun::factory()->create(['user_id' => $attributes['user_id']])->id,
            'offer_key' => fake()->unique()->regexify('[a-f0-9]{8}'),
            'template' => 'taxi-trip',
            'kind' => 'taxi',
            'region' => 'zlomowo',
            'to_region' => 'kruszyn',
            'distance_km' => 76,
            'amount' => 2,
            'capacity' => 'seats',
            'rarity' => 'ordinary',
            'phase' => 'day',
            'fuel_ml' => 4_560,
            'start_ms' => 0,
            'finish_ms' => 1_800_000,
            'reward_cents' => 41_800,
            'status' => Gig::STATUS_ACTIVE,
        ];
    }
}
