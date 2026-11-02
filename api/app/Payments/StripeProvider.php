<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\PaymentAttempt;

/**
 * Stripe, the company that takes the cards (and BLIK, and the bank transfers) for the packs of coins and the pass.
 *
 * Stage S1 has the half that needs no account: the webhook (the signature, and the events turned into the events of the game). The page where the driver pays, and asking
 * Stripe about it, come with the test keys in stage S2 (they are plain HTTP calls through the Http client of Laravel; the library of Stripe is not used), and until then this
 * provider is not `ready()`, so no driver is sent to it and nothing is offered for sale.
 */
final class StripeProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'stripe';
    }

    public function ready(): bool
    {
        // S2: the creation of the page of payment.
        return false;
    }

    /**
     * The mode comes from the key of the account, so it cannot disagree with it: `sk_test_` and `rk_test_` are test money, `sk_live_` and `rk_live_` real money.
     *
     * @throws PaymentsUnavailable when there is no key, or it is not one of Stripe's
     */
    public function livemode(): bool
    {
        $key = (string) config('ride.premium.payments.stripe.secret');

        return match (true) {
            str_starts_with($key, 'sk_live_'), str_starts_with($key, 'rk_live_') => true,
            str_starts_with($key, 'sk_test_'), str_starts_with($key, 'rk_test_') => false,
            default => throw new PaymentsUnavailable('There is no key of Stripe, or it is not one.'),
        };
    }

    /**
     * Whether this installation takes money in the mode of the key: real money only in production, test money everywhere else (and in production too when it is said to
     * be a demonstration, `PAYMENTS_ALLOW_TEST`). Live money in a development installation is the mistake this exists to stop.
     */
    public function modeAllowed(): bool
    {
        try {
            $live = $this->livemode();
        } catch (PaymentsUnavailable) {
            return false;
        }

        return $live ? app()->isProduction() : (! app()->isProduction() || (bool) config('ride.premium.payments.allow_test'));
    }

    public function createCheckout(PaymentAttempt $attempt, string $productName, string $successUrl, string $cancelUrl): CheckoutSession
    {
        throw new PaymentsUnavailable('The page of payment of Stripe is not built yet.');
    }

    public function retrieveSession(string $sessionRef): ?SessionState
    {
        throw new PaymentsUnavailable('Asking Stripe about a payment is not built yet.');
    }

    public function parseWebhook(string $payload, ?string $signature): ?ProviderEvent
    {
        if (! $this->modeAllowed()) {
            throw new PaymentsUnavailable('The key of Stripe is missing, or its mode is not allowed here.');
        }

        $secrets = array_values(array_filter(array_map('trim', explode(',', (string) config('ride.premium.payments.stripe.webhook_secret')))));

        if ($secrets === []) {
            throw new PaymentsUnavailable('The secret of the webhook of Stripe is missing.');
        }

        $event = StripeWebhook::verify($payload, $signature, $secrets, (int) config('ride.premium.payments.stripe.webhook_tolerance', 300));

        return StripeWebhook::normalise($event);
    }
}
