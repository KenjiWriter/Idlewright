<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentEvent>
 */
class PaymentEventFactory extends Factory
{
    /**
     * An event that arrived and has not been worked yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'stripe',
            'event_id' => 'evt_'.fake()->unique()->bothify('????########'),
            'type' => 'payment.paid',
            'source_type' => 'checkout.session.completed',
            'livemode' => false,
            'state' => PaymentEvent::STATE_RECEIVED,
            'tries' => 0,
            'received_at' => now(),
        ];
    }
}
