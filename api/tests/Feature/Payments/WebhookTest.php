<?php

declare(strict_types=1);

use App\Exceptions\IdempotencyConflictException;
use App\Games\CurrentGame;
use App\Models\Game;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger;
use App\Services\Purchases;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

require_once __DIR__.'/../../Support/PaymentHelpers.php';

// What Stripe tells the game at the webhook: that a driver paid, that the page ran out, that the payment is on its way. The coins are paid out from the signed event, once,
// as the attempt says the driver was offered, whatever order the events come in and however many times, and an event that is not what was offered gives nothing.

beforeEach(fn () => stripeIsSetUp());
afterEach(fn () => Carbon::setTestNow());

describe('a payment that was made', function () {
    it('gives the coins, and records the purchase with what the driver was offered and confirmed', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver, ['product' => 'coins-medium', 'coins' => 220, 'amount_minor' => 1999, 'waiver_at' => now()]);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk()->assertJson(['received' => true, 'result' => 'processed']);

        $purchase = Purchase::query()->sole();
        expect(coinsOf($driver))->toBe(220)
            ->and($purchase->only(['user_id', 'product', 'coins', 'amount_minor', 'currency', 'provider', 'provider_ref', 'status', 'livemode', 'game_id', 'attempt_id', 'provider_session', 'terms_version']))
            ->toBe(['user_id' => $driver->id, 'product' => 'coins-medium', 'coins' => 220, 'amount_minor' => 1999, 'currency' => 'PLN', 'provider' => 'stripe', 'provider_ref' => 'pi_'.$attempt->id, 'status' => 'paid', 'livemode' => false, 'game_id' => 1, 'attempt_id' => $attempt->id, 'provider_session' => $attempt->provider_session, 'terms_version' => '2026-10-23'])
            ->and($purchase->consent_at)->not->toBeNull()->and($purchase->adult_declared_at)->not->toBeNull()->and($purchase->waiver_at)->not->toBeNull()
            ->and($attempt->refresh()->state)->toBe('paid')->and($attempt->provider_ref)->toBe('pi_'.$attempt->id)->and($attempt->completed_at)->not->toBeNull()
            ->and(PaymentEvent::query()->sole()->only(['provider', 'type', 'source_type', 'state', 'tries']))->toBe(['provider' => 'stripe', 'type' => 'payment.paid', 'source_type' => 'checkout.session.completed', 'state' => 'processed', 'tries' => 1]);
    });

    it('gives the coins once when Stripe tells the same event again', function () {
        $driver = User::factory()->create();
        $event = stripeEvent('checkout.session.completed', stripeSession(aStripeAttempt($driver)));

        sendToTheWebhook($event)->assertOk()->assertJson(['result' => 'processed']);
        sendToTheWebhook($event)->assertOk()->assertJson(['result' => 'duplicate']);

        expect(coinsOf($driver))->toBe(50)->and(Purchase::query()->count())->toBe(1)->and(PaymentEvent::query()->count())->toBe(1);
    });

    it('gives the coins once when two different events speak of the same payment', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();
        sendToTheWebhook(stripeEvent('checkout.session.async_payment_succeeded', stripeSession($attempt)))->assertOk()->assertJson(['result' => 'processed']);

        expect(coinsOf($driver))->toBe(50)->and(Purchase::query()->count())->toBe(1)->and(PaymentEvent::query()->count())->toBe(2);
    });

    it('gives the coins once when the driver coming back and the webhook both learn of the payment', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver);
        // The payment was given out, and the game stopped before it wrote that the attempt is paid.
        app(Purchases::class)->fulfil($driver->id, $attempt->product, 'stripe', 'pi_'.$attempt->id, attempt: $attempt);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();

        expect(coinsOf($driver))->toBe(50)->and(Purchase::query()->count())->toBe(1)->and($attempt->refresh()->state)->toBe('paid');
    });

    it('pays out what the driver was offered, whatever the rules say by the time the payment comes', function () {
        $driver = User::factory()->create();
        // The small pack gave 50 coins for 4.99 when the driver was sent to pay; here the attempt says 55, and a pack that the rules no longer know.
        $attempt = aStripeAttempt($driver, ['product' => 'coins-retired', 'coins' => 55, 'amount_minor' => 450]);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk()->assertJson(['result' => 'processed']);

        expect(coinsOf($driver))->toBe(55)->and(Purchase::query()->sole()->only(['product', 'coins', 'amount_minor']))->toBe(['product' => 'coins-retired', 'coins' => 55, 'amount_minor' => 450]);
    });

    it('adds the days of a pass, and a second pass is added to the end of the first', function () {
        $driver = User::factory()->create();
        $first = aStripePassAttempt($driver, ['request_id' => 'a']);
        $second = aStripePassAttempt($driver, ['request_id' => 'b']);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($first)))->assertOk();
        $afterOne = Wallet::query()->find($driver->id)->infinity_until;
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($second)))->assertOk();
        $afterTwo = Wallet::query()->find($driver->id)->infinity_until;

        expect((int) round(now()->diffInDays($afterOne, true)))->toBe(30)
            ->and((int) round($afterOne->diffInDays($afterTwo, true)))->toBe(30)
            ->and(Purchase::query()->pluck('grant_days')->all())->toBe([30, 30])
            ->and(coinsOf($driver))->toBe(0);
    });

    it('gives nothing for a payment that is on its way, and gives it when it arrives', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt, ['payment_status' => 'unpaid'])))->assertOk();
        expect($attempt->refresh()->state)->toBe('processing')->and(coinsOf($driver))->toBe(0)->and(Purchase::query()->count())->toBe(0);

        sendToTheWebhook(stripeEvent('checkout.session.async_payment_succeeded', stripeSession($attempt)))->assertOk();
        expect($attempt->refresh()->state)->toBe('paid')->and(coinsOf($driver))->toBe(50);
    });

    it('ends the attempt when the payment that was on its way failed, or the page ran out', function () {
        $failed = aStripeAttempt(null, ['state' => 'processing']);
        $ranOut = aStripeAttempt();

        sendToTheWebhook(stripeEvent('checkout.session.async_payment_failed', stripeSession($failed, ['payment_status' => 'unpaid'])))->assertOk();
        sendToTheWebhook(stripeEvent('checkout.session.expired', stripeSession($ranOut, ['payment_status' => 'unpaid', 'status' => 'expired'])))->assertOk();

        expect($failed->refresh()->state)->toBe('failed')->and($ranOut->refresh()->state)->toBe('expired')->and(Purchase::query()->count())->toBe(0);
    });

    it('does not take back a payment that was made when an old event says the page ran out', function () {
        $attempt = aStripeAttempt();

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();
        sendToTheWebhook(stripeEvent('checkout.session.expired', stripeSession($attempt)))->assertOk();

        expect($attempt->refresh()->state)->toBe('paid');
    });

    it('gives the coins for a payment that came after the attempt was marked expired, because the money is real', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver, ['state' => 'expired']);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();

        expect(coinsOf($driver))->toBe(50)->and($attempt->refresh()->state)->toBe('paid');
    });

    it('puts a payment for an account that was erased in the books, gives nothing, and marks it for a person', function () {
        $attempt = aStripeAttempt(null, ['user_id' => null]);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk()->assertJson(['result' => 'processed']);

        $purchase = Purchase::query()->sole();
        expect($purchase->only(['user_id', 'status', 'review_reason', 'amount_minor', 'game_id']))->toBe(['user_id' => null, 'status' => 'paid', 'review_reason' => 'account_erased', 'amount_minor' => 499, 'game_id' => 1])
            ->and($attempt->refresh()->state)->toBe('paid');
    });
});

describe('a payment that is not what was offered', function () {
    it('gives nothing for another amount or another currency, keeps the attempt as it is, and tells Stripe not to try again', function (array $session) {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt, $session)))->assertOk()->assertJson(['result' => 'rejected']);

        expect(coinsOf($driver))->toBe(0)->and(Purchase::query()->count())->toBe(0)
            ->and($attempt->refresh()->state)->toBe('mismatch')
            ->and(PaymentEvent::query()->sole()->only(['state', 'note']))->toBe(['state' => 'rejected', 'note' => 'mismatch']);

        // And a later event about the same page does not make it right.
        sendToTheWebhook(stripeEvent('checkout.session.async_payment_succeeded', stripeSession($attempt)))->assertOk();
        expect(coinsOf($driver))->toBe(0);
    })->with([
        'less was paid' => [['amount_total' => 100]],
        'more was paid' => [['amount_total' => 99_999]],
        'in another currency' => [['currency' => 'eur']],
    ]);

    it('gives nothing for a payment made in the other mode than the attempt', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver, ['livemode' => true]);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk()->assertJson(['result' => 'rejected']);

        expect(coinsOf($driver))->toBe(0)->and($attempt->refresh()->state)->toBe('created');
    });

    it('ignores an event of the other mode than the key of the server', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt), ['livemode' => true]))->assertOk()->assertJson(['result' => 'other_mode']);

        expect(coinsOf($driver))->toBe(0)->and(PaymentEvent::query()->count())->toBe(0);
    });

    it('ignores a payment for a page the game never asked for', function () {
        sendToTheWebhook(stripeEvent('checkout.session.completed', ['id' => 'cs_test_payment_link', 'payment_status' => 'paid', 'payment_intent' => 'pi_x', 'amount_total' => 499, 'currency' => 'pln']))
            ->assertOk()->assertJson(['result' => 'ignored']);

        expect(Purchase::query()->count())->toBe(0)->and(PaymentEvent::query()->sole()->state)->toBe('ignored');
    });

    it('has no use for an event of a kind it did not ask for', function () {
        sendToTheWebhook(stripeEvent('customer.created', ['id' => 'cus_1']))->assertOk()->assertJson(['result' => 'ignored']);

        expect(PaymentEvent::query()->count())->toBe(0);
    });
});

describe('who may tell the game', function () {
    it('refuses a request that is not signed with the secret, and writes nothing', function (callable $send) {
        $attempt = aStripeAttempt();

        $send(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertStatus(400)->assertJsonPath('code', 'invalid_webhook');

        expect(Purchase::query()->count())->toBe(0)->and(PaymentEvent::query()->count())->toBe(0)->and($attempt->refresh()->state)->toBe('created');
    })->with([
        'another secret' => [fn (array $event) => sendToTheWebhook($event, secret: 'whsec_somebody_else')],
        'a time long ago' => [fn (array $event) => sendToTheWebhook($event, time() - 3_600)],
        'no signature at all' => [fn (array $event) => test()->call('POST', '/api/v1/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($event))],
    ]);

    it('answers 503, which Stripe tries again after, while the server has no secret or no key', function (array $config) {
        config($config);

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession(aStripeAttempt())))->assertStatus(503)->assertJsonPath('code', 'payments_unavailable');
    })->with([
        'no secret' => [['ride.premium.payments.stripe.webhook_secret' => null]],
        'no key' => [['ride.premium.payments.stripe.secret' => null]],
        'live money in a development installation' => [['ride.premium.payments.stripe.secret' => 'sk_live_abc']],
    ]);

    it('lets a test key in production in only when the installation is said to be a demonstration', function () {
        app()['env'] = 'production';
        $attempt = aStripeAttempt();

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertStatus(503);

        config(['ride.premium.payments.allow_test' => true]);
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();
    });

    it('does not need a game to be claimed by the host, because the game of a payment is the one of its attempt', function () {
        $ships = aSecondPayingGame();
        app()['env'] = 'production';
        config(['ride.premium.payments.allow_test' => true]);
        $driver = app(CurrentGame::class)->within($ships, fn () => User::factory()->create());
        $attempt = app(CurrentGame::class)->within($ships, fn () => aStripeAttempt($driver, ['game_id' => $ships->id]));

        // A request of the API for this host is for nobody...
        $this->getJson('http://nobody.example/api/v1/meta/game')->assertStatus(404)->assertJsonPath('code', 'unknown_game');
        // ...and the webhook is not stopped by it.
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)), url: 'http://nobody.example/api/v1/webhooks/stripe')->assertOk()->assertJson(['result' => 'processed']);

        $purchase = Purchase::query()->withoutGlobalScopes()->sole();
        expect($purchase->game_id)->toBe($ships->id)
            ->and(Wallet::query()->find($driver->id)->premium_coins)->toBe(50);
    });

    it('pays the coins in the game of the attempt, not in the game that happens to be in play', function () {
        $ships = aSecondPayingGame();
        $shipsDriver = app(CurrentGame::class)->within($ships, fn () => User::factory()->create());
        $firstDriver = User::factory()->create();
        $attempt = app(CurrentGame::class)->within($ships, fn () => aStripeAttempt($shipsDriver, ['game_id' => $ships->id]));

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();

        expect(Wallet::query()->find($shipsDriver->id)->premium_coins)->toBe(50)
            ->and(Wallet::query()->find($firstDriver->id)?->premium_coins ?? 0)->toBe(0)
            ->and(Purchase::query()->withoutGlobalScopes()->sole()->game_id)->toBe($ships->id)
            ->and(app(CurrentGame::class)->id())->toBe(1);
    });
});

describe('events that come in the wrong order', function () {
    it('keeps a refund that came before its payment, and works it when the payment is there', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver);
        $payment = 'pi_'.$attempt->id;

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 499, 499)))->assertOk()->assertJson(['result' => 'deferred']);
        expect(PaymentEvent::query()->sole()->state)->toBe('deferred');

        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();
        expect(coinsOf($driver))->toBe(50);

        expect(Artisan::call('payments:reprocess'))->toBe(0)
            ->and(PaymentEvent::query()->where('type', 'payment.refunded')->sole()->state)->toBe('processed')
            ->and(Purchase::query()->sole()->status)->toBe('refunded')
            ->and(coinsOf($driver))->toBe(0);
    });

    it('works a waiting event again until it is settled, and stops after three days', function () {
        $attempt = aStripeAttempt();
        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge('pi_'.$attempt->id, 499, 499)))->assertOk();

        Artisan::call('payments:reprocess');
        expect(PaymentEvent::query()->sole()->only(['state', 'tries']))->toBe(['state' => 'deferred', 'tries' => 2]);

        Carbon::setTestNow(now()->addDays(4));
        Artisan::call('payments:reprocess');
        expect(PaymentEvent::query()->sole()->tries)->toBe(2);
    });

    it('is told to try again when the game itself fails, and works the event when Stripe does', function () {
        $driver = User::factory()->create();
        $attempt = aStripeAttempt($driver);
        $event = stripeEvent('checkout.session.completed', stripeSession($attempt));
        // A purchase of this payment for another driver: the game cannot decide whose it is, and says so.
        app(Purchases::class)->fulfil(User::factory()->create()->id, 'coins-small', 'stripe', 'pi_'.$attempt->id);

        $this->withoutExceptionHandling();
        expect(fn () => sendToTheWebhook($event))->toThrow(IdempotencyConflictException::class);

        expect(PaymentEvent::query()->sole()->state)->toBe('failed')->and(coinsOf($driver))->toBe(0);
    });
});

describe('the housekeeping', function () {
    it('keeps what the books of the shop say through the ledger', function () {
        $driver = User::factory()->create();
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession(aStripeAttempt($driver))))->assertOk();

        expect((new Ledger)->isConsistent($driver->id, Ledger::PREMIUM))->toBeTrue()
            ->and(LedgerEntry::query()->where('user_id', $driver->id)->where('currency', 'premium')->value('idem_key'))->toStartWith('purchase:stripe:pi_');
    });
});
