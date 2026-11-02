<?php

declare(strict_types=1);

use App\Games\CurrentGame;
use App\Models\Game;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Payments\FakeProvider;
use App\Services\Ledger;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// What the tests of the payments share: the fake provider switched on, Stripe set up the way a server has it (a test key and the secret of the webhook), a signed event of Stripe
// and the call of the webhook, and an attempt that Stripe can speak of.

/** The secret that signs the events of these tests. */
const STRIPE_TEST_WEBHOOK_SECRET = 'whsec_test_secret';

/** The game sells with the provider that takes no money (it is on, because a game that was never told is on outside production). */
function payWithTheFakeProvider(): FakeProvider
{
    config(['ride.premium.provider' => 'fake']);

    return app(FakeProvider::class);
}

/** The server has a test key of Stripe and the secret of its webhook. */
function stripeIsSetUp(string $key = 'sk_test_abc123'): void
{
    config([
        'ride.premium.provider' => 'stripe',
        'ride.premium.payments.stripe.secret' => $key,
        'ride.premium.payments.stripe.webhook_secret' => STRIPE_TEST_WEBHOOK_SECRET,
    ]);
}

/**
 * An event as Stripe sends it.
 *
 * @param  array<string, mixed>  $object  what is in `data.object`
 * @param  array<string, mixed>  $extra  more of the envelope (a fixed `id`, `livemode`)
 * @return array<string, mixed>
 */
function stripeEvent(string $type, array $object, array $extra = []): array
{
    return ['id' => 'evt_'.Str::lower(Str::random(14)), 'object' => 'event', 'type' => $type, 'livemode' => false, 'created' => time(), 'data' => ['object' => $object], ...$extra];
}

/** The page of payment of an attempt, as Stripe shows it in an event: paid, unless it is said otherwise. @param  array<string, mixed>  $extra */
function stripeSession(PaymentAttempt $attempt, array $extra = []): array
{
    return [
        'id' => $attempt->provider_session,
        'object' => 'checkout.session',
        'status' => 'complete',
        'payment_status' => 'paid',
        'payment_intent' => 'pi_'.$attempt->id,
        'amount_total' => $attempt->amount_minor,
        'currency' => strtolower($attempt->currency),
        'client_reference_id' => $attempt->public_id,
        'livemode' => false,
        ...$extra,
    ];
}

/** A charge as Stripe shows it in the event of a refund: `$refunded` of it is given back. */
function stripeCharge(string $paymentRef, int $amount, int $refunded, string $currency = 'pln'): array
{
    return ['id' => 'ch_'.$paymentRef, 'object' => 'charge', 'payment_intent' => $paymentRef, 'amount' => $amount, 'amount_refunded' => $refunded, 'currency' => $currency, 'refunded' => $refunded >= $amount];
}

/** A dispute as Stripe shows it. */
function stripeDispute(string $paymentRef, int $amount, string $status = 'needs_response', string $currency = 'pln'): array
{
    return ['id' => 'du_'.$paymentRef, 'object' => 'dispute', 'charge' => 'ch_'.$paymentRef, 'payment_intent' => $paymentRef, 'amount' => $amount, 'currency' => $currency, 'status' => $status];
}

/**
 * The call of the webhook as Stripe makes it: the body as JSON, signed with the secret and the time.
 *
 * @param  array<string, mixed>  $event
 */
function sendToTheWebhook(array $event, ?int $time = null, string $secret = STRIPE_TEST_WEBHOOK_SECRET, string $url = '/api/v1/webhooks/stripe'): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $time ??= time();

    return test()->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$body}", $secret)], $body);
}

/** An attempt at a page of Stripe that nothing came back for: the small pack, 4.99 PLN, test money. @param  array<string, mixed>  $attributes */
function aStripeAttempt(?User $user = null, array $attributes = []): PaymentAttempt
{
    return PaymentAttempt::factory()->create([
        'user_id' => ($user ?? User::factory()->create())->id,
        'provider' => 'stripe',
        'provider_session' => 'cs_test_'.Str::lower(Str::random(16)),
        'terms_version' => '2026-10-23',
        'consent_at' => now(),
        'adult_declared_at' => now(),
        ...$attributes,
    ]);
}

/** An attempt to buy the pass for 30 days at 19.99 PLN. @param  array<string, mixed>  $attributes */
function aStripePassAttempt(?User $user = null, array $attributes = []): PaymentAttempt
{
    return aStripeAttempt($user, ['product' => 'infinity', 'kind' => PaymentAttempt::KIND_PASS, 'coins' => 0, 'grant_days' => 30, 'amount_minor' => 1999, ...$attributes]);
}

/** The premium coins a driver has. */
function coinsOf(User $user): int
{
    return (new Ledger)->premiumBalance($user->id);
}

/** A second game that answers on a domain of its own, live, with the content of the repository as its pack (so that it has rules to read). @param  array<string, mixed>  $attributes */
function aSecondPayingGame(array $attributes = []): Game
{
    $game = Game::query()->create([...['slug' => 'ships', 'name' => 'Ships', 'domain' => 'ships.test', 'locale' => 'en', 'theme_color' => '#0b3d5c', 'status' => Game::STATUS_LIVE], ...$attributes]);

    app(CurrentGame::class)->within($game, fn () => contentPacks()->import(repositoryContent(), '2026.11.2', activate: true, checkTables: false));

    return $game;
}
