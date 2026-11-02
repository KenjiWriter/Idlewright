<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\PaymentAttempt;

/**
 * What the game needs from a company that takes the money: a page where a driver pays, a way to ask what became of it, and a way to read what the company tells the game
 * (the webhook). Everything else (the book of the purchases, the coins, refunds, limits) is the game's and is the same for every provider, so a provider can be replaced
 * without touching it.
 */
interface PaymentProvider
{
    /** The name the book of purchases records the provider under (`purchases.provider`). */
    public function key(): string;

    /** Whether a driver can be sent to pay with it now: it is set up, and in a mode that this installation allows. */
    public function ready(): bool;

    /** Whether the money is real (the provider's live mode) or test money. */
    public function livemode(): bool;

    /**
     * Makes the page where the driver pays for the attempt, for exactly what the attempt says. The addresses are those of the game the driver plays (built by the server,
     * never taken from a request).
     *
     * @throws PaymentsUnavailable when the provider cannot be reached or refuses
     */
    public function createCheckout(PaymentAttempt $attempt, string $productName, string $successUrl, string $cancelUrl): CheckoutSession;

    /**
     * Asks the provider what became of a page of payment; null when it does not know it.
     *
     * @throws PaymentsUnavailable when the provider cannot be reached
     */
    public function retrieveSession(string $sessionRef): ?SessionState;

    /**
     * Checks that what arrived at the webhook comes from the provider and is not old, and turns it into an event of the game; null for an event the game does not use.
     *
     * @throws InvalidWebhook when the signature is wrong, missing or too old, or the body is not what the provider sends
     * @throws PaymentsUnavailable when the provider is not set up
     */
    public function parseWebhook(string $payload, ?string $signature): ?ProviderEvent;
}
