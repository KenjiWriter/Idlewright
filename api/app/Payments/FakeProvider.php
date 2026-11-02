<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\PaymentAttempt;
use Illuminate\Support\Str;

/**
 * A provider that takes no money: it gives a page that does not exist and remembers what it was asked for, so that the whole of the game side of a payment can be run
 * in development and in tests (`pay()` is the driver finishing the page). It is refused in production.
 *
 * It keeps its pages in memory, so it is one object for the whole process (the container makes it a singleton) and forgets them when the process ends.
 */
final class FakeProvider implements PaymentProvider
{
    /** @var array<string, array{status: string, payment: string|null, amount: int, currency: string}> */
    private array $sessions = [];

    private bool $failing = false;

    /** @var list<array{attempt: string, name: string, success: string, cancel: string}> what it was asked for, to see what the game asks of a provider */
    private array $asked = [];

    public function key(): string
    {
        return 'fake';
    }

    public function ready(): bool
    {
        return ! app()->isProduction();
    }

    public function livemode(): bool
    {
        return false;
    }

    public function createCheckout(PaymentAttempt $attempt, string $productName, string $successUrl, string $cancelUrl): CheckoutSession
    {
        if ($this->failing) {
            throw new PaymentsUnavailable('The fake provider is told to fail.');
        }

        $ref = 'fake_cs_'.Str::lower(Str::random(24));
        $this->asked[] = ['attempt' => $attempt->public_id, 'name' => $productName, 'success' => $successUrl, 'cancel' => $cancelUrl];
        $this->sessions[$ref] = ['status' => SessionState::OPEN, 'payment' => null, 'amount' => $attempt->amount_minor, 'currency' => $attempt->currency];

        return new CheckoutSession($ref, "https://pay.fake.invalid/{$ref}", now()->addMinutes(30));
    }

    public function retrieveSession(string $sessionRef): ?SessionState
    {
        $session = $this->sessions[$sessionRef] ?? null;

        return $session === null ? null : new SessionState($sessionRef, $session['status'], $session['payment'], $session['amount'], $session['currency'], false);
    }

    public function parseWebhook(string $payload, ?string $signature): ?ProviderEvent
    {
        throw new InvalidWebhook('The fake provider has no webhook.');
    }

    /** What the game asked for with the last page, or null when it asked for none. @return array{attempt: string, name: string, success: string, cancel: string}|null */
    public function lastAsked(): ?array
    {
        return $this->asked === [] ? null : $this->asked[array_key_last($this->asked)];
    }

    /** The driver paid on the page; the payment is named and returned. */
    public function pay(string $sessionRef): string
    {
        $this->sessions[$sessionRef]['status'] = SessionState::PAID;

        return $this->sessions[$sessionRef]['payment'] ??= 'fake_pi_'.Str::lower(Str::random(24));
    }

    /** The provider cannot be reached from now on (or can again): to see what the game does when it is down. */
    public function fail(bool $failing = true): void
    {
        $this->failing = $failing;
    }

    /** The page ran out. */
    public function expire(string $sessionRef): void
    {
        $this->sessions[$sessionRef]['status'] = SessionState::EXPIRED;
    }
}
