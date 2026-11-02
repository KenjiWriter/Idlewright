<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    /**
     * An attempt to buy the small pack of the shipped catalog, paid by the fake provider, that nothing has come back for.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'game_id' => 1,
            'user_id' => User::factory(),
            'request_id' => 'req-'.fake()->unique()->bothify('????########'),
            'product' => 'coins-small',
            'kind' => PaymentAttempt::KIND_COINS,
            'coins' => 50,
            'grant_days' => null,
            'amount_minor' => 499,
            'currency' => 'PLN',
            'provider' => 'fake',
            'provider_session' => 'cs_test_'.fake()->unique()->bothify('????########'),
            'state' => PaymentAttempt::STATE_CREATED,
            'livemode' => false,
            'locale' => 'pl',
            'expires_at' => now()->addMinutes(30),
        ];
    }

    /** The pass of the endless refill. */
    public function pass(int $days = 30, int $amountMinor = 1999): static
    {
        return $this->state(fn (): array => ['product' => 'infinity', 'kind' => PaymentAttempt::KIND_PASS, 'coins' => 0, 'grant_days' => $days, 'amount_minor' => $amountMinor]);
    }
}
