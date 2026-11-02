<?php

declare(strict_types=1);

use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountErasure;
use App\Services\Ledger;
use App\Services\Premium;
use App\Services\Purchases;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../../Support/PaymentHelpers.php';

// What happens when a payment is taken back: given back by the shop (a refund, whole or in part), or disputed by the payer with their bank. The coins and the days it gave go
// with it; a driver who spent the coins owes them, and cannot buy until the dispute is decided.

beforeEach(fn () => stripeIsSetUp());
afterEach(fn () => Carbon::setTestNow());

/** A driver who paid for a pack through Stripe: the driver, the payment, and the purchase. @return array{0: User, 1: string, 2: Purchase} */
function aDriverWhoPaid(string $product = 'coins-medium', int $coins = 220, int $amount = 1999): array
{
    $driver = User::factory()->create();
    $attempt = aStripeAttempt($driver, ['product' => $product, 'coins' => $coins, 'amount_minor' => $amount]);
    sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();

    return [$driver, 'pi_'.$attempt->id, Purchase::query()->latest('id')->firstOrFail()];
}

/** A driver who spent coins. */
function spend(User $driver, int $coins): void
{
    app(Premium::class)->spend($driver->id, $coins, 'speedup', 'spend:'.uniqid());
}

describe('a refund', function () {
    it('takes the coins back and marks the purchase', function () {
        [$driver, $payment, $purchase] = aDriverWhoPaid();

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1999)))->assertOk()->assertJson(['result' => 'processed']);

        $purchase->refresh();
        expect(coinsOf($driver))->toBe(0)
            ->and($purchase->only(['status', 'refunded_amount_minor']))->toBe(['status' => 'refunded', 'refunded_amount_minor' => 1999])
            ->and($purchase->refunded_at)->not->toBeNull()
            ->and((new Ledger)->isConsistent($driver->id, Ledger::PREMIUM))->toBeTrue()
            ->and(LedgerEntry::query()->where('user_id', $driver->id)->where('reason', 'refund')->value('idem_key'))->toStartWith('refund:stripe:pi_');
    });

    it('leaves the driver in debt for coins they spent, and the next purchase pays it off', function () {
        [$driver, $payment] = aDriverWhoPaid();
        spend($driver, 200);

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1999)))->assertOk();
        expect(coinsOf($driver))->toBe(-200);

        $again = aStripeAttempt($driver, ['request_id' => 'second']);
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($again)))->assertOk();

        // The 50 coins of the small pack go to the debt first.
        expect(coinsOf($driver))->toBe(-150);
    });

    it('changes nothing when Stripe tells it again', function () {
        [$driver, $payment] = aDriverWhoPaid();
        $event = stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1999));

        sendToTheWebhook($event)->assertOk();
        sendToTheWebhook($event)->assertOk()->assertJson(['result' => 'duplicate']);
        // The same refund told by another event (Stripe makes two for some refunds).
        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1999)))->assertOk();

        expect(coinsOf($driver))->toBe(0)->and(LedgerEntry::query()->where('reason', 'refund')->count())->toBe(1);
    });

    it('leaves the coins to the driver when only a part of the price is given back, and marks the purchase for a person', function () {
        [$driver, $payment, $purchase] = aDriverWhoPaid();

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 500)))->assertOk()->assertJson(['result' => 'processed']);

        $purchase->refresh();
        expect(coinsOf($driver))->toBe(220)
            ->and($purchase->only(['status', 'refunded_amount_minor', 'review_reason']))->toBe(['status' => 'partially_refunded', 'refunded_amount_minor' => 500, 'review_reason' => 'partial_refund']);
    });

    it('takes the coins back when the rest of the price is given back later, and never goes back to a smaller part', function () {
        [$driver, $payment, $purchase] = aDriverWhoPaid();

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1000)))->assertOk();
        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 400)))->assertOk();
        expect($purchase->refresh()->refunded_amount_minor)->toBe(1000);

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1999)))->assertOk();

        expect($purchase->refresh()->only(['status', 'refunded_amount_minor', 'review_reason']))->toBe(['status' => 'refunded', 'refunded_amount_minor' => 1999, 'review_reason' => null])
            ->and(coinsOf($driver))->toBe(0);
    });

    it('is recorded for a purchase whose account was erased, with no coins to take', function () {
        [$driver, $payment, $purchase] = aDriverWhoPaid();
        app(AccountErasure::class)->erase($driver);

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1999)))->assertOk();

        expect($purchase->refresh()->only(['user_id', 'status']))->toBe(['user_id' => null, 'status' => 'refunded']);
    });

    it('is refused when it is of the other mode than the purchase', function () {
        [, $payment, $purchase] = aDriverWhoPaid();

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1999), ['livemode' => true]))->assertOk()->assertJson(['result' => 'other_mode']);

        expect($purchase->refresh()->status)->toBe('paid');
    });

    it('takes back the days of the pass it gave, and no others', function () {
        $driver = User::factory()->create();
        $first = aStripePassAttempt($driver, ['request_id' => 'a']);
        $second = aStripePassAttempt($driver, ['request_id' => 'b']);
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($first)))->assertOk();
        $afterOne = Wallet::query()->find($driver->id)->infinity_until;
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($second)))->assertOk();

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge('pi_'.$second->id, 1999, 1999)))->assertOk();

        // The second pass is gone and the first one is as it was.
        expect(Wallet::query()->find($driver->id)->infinity_until->equalTo($afterOne))->toBeTrue();

        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge('pi_'.$first->id, 1999, 1999)))->assertOk();

        expect(Wallet::query()->find($driver->id)->infinity_until->isFuture())->toBeFalse();
    });

    it('ends at once a pass that was bought before the game kept its days', function () {
        $driver = User::factory()->create();
        $purchase = app(Purchases::class)->fulfilPass($driver->id, 'operator', 'gift-1');
        $purchase->forceFill(['grant_days' => null])->save();

        app(Purchases::class)->refund($purchase);

        expect(Wallet::query()->find($driver->id)->infinity_until->isFuture())->toBeFalse();
    });
});

describe('a dispute', function () {
    it('takes the coins at once, blocks buying on the account, and says so', function () {
        [$driver, $payment, $purchase] = aDriverWhoPaid();

        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute($payment, 1999)))->assertOk()->assertJson(['result' => 'processed']);

        $purchase->refresh();
        expect(coinsOf($driver))->toBe(0)
            ->and($purchase->only(['status', 'dispute_state']))->toBe(['status' => 'disputed', 'dispute_state' => 'open'])
            ->and(Wallet::query()->find($driver->id)->purchases_blocked_at)->not->toBeNull()
            ->and(LedgerEntry::query()->where('user_id', $driver->id)->where('reason', 'dispute')->exists())->toBeTrue();

        payWithTheFakeProvider();
        $this->postJson('/api/v1/premium/checkout', ['product' => 'coins-small', 'request_id' => 'x', 'accept_terms' => true, 'adult_or_guardian' => true], bearerFor($driver))
            ->assertStatus(422)->assertJsonPath('code', 'purchases_blocked');
    });

    it('gives the coins back and lifts the block when the shop wins', function () {
        [$driver, $payment, $purchase] = aDriverWhoPaid();
        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute($payment, 1999)))->assertOk();

        sendToTheWebhook(stripeEvent('charge.dispute.closed', stripeDispute($payment, 1999, 'won')))->assertOk();

        expect(coinsOf($driver))->toBe(220)
            ->and($purchase->refresh()->only(['status', 'dispute_state']))->toBe(['status' => 'paid', 'dispute_state' => 'won'])
            ->and(Wallet::query()->find($driver->id)->purchases_blocked_at)->toBeNull()
            ->and((new Ledger)->isConsistent($driver->id, Ledger::PREMIUM))->toBeTrue();
    });

    it('gives the coins back when the inquiry was closed without becoming a dispute', function () {
        [$driver, $payment] = aDriverWhoPaid();
        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute($payment, 1999)))->assertOk();

        sendToTheWebhook(stripeEvent('charge.dispute.closed', stripeDispute($payment, 1999, 'warning_closed')))->assertOk();

        expect(coinsOf($driver))->toBe(220);
    });

    it('counts the purchase as given back, and keeps the block, when the shop loses', function () {
        [$driver, $payment, $purchase] = aDriverWhoPaid();
        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute($payment, 1999)))->assertOk();

        sendToTheWebhook(stripeEvent('charge.dispute.closed', stripeDispute($payment, 1999, 'lost')))->assertOk();

        expect(coinsOf($driver))->toBe(0)
            ->and($purchase->refresh()->only(['status', 'dispute_state', 'refunded_amount_minor']))->toBe(['status' => 'refunded', 'dispute_state' => 'lost', 'refunded_amount_minor' => 1999])
            ->and(Wallet::query()->find($driver->id)->purchases_blocked_at)->not->toBeNull();
    });

    it('keeps the block while another dispute is open, and lifts it when the last is won', function () {
        $driver = User::factory()->create();
        $one = aStripeAttempt($driver, ['request_id' => 'a']);
        $two = aStripeAttempt($driver, ['request_id' => 'b']);
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($one)))->assertOk();
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($two)))->assertOk();
        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute('pi_'.$one->id, 499)))->assertOk();
        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute('pi_'.$two->id, 499)))->assertOk();

        sendToTheWebhook(stripeEvent('charge.dispute.closed', stripeDispute('pi_'.$one->id, 499, 'won')))->assertOk();
        expect(Wallet::query()->find($driver->id)->purchases_blocked_at)->not->toBeNull();

        sendToTheWebhook(stripeEvent('charge.dispute.closed', stripeDispute('pi_'.$two->id, 499, 'won')))->assertOk();
        expect(Wallet::query()->find($driver->id)->purchases_blocked_at)->toBeNull()->and(coinsOf($driver))->toBe(100);
    });

    it('does not take the coins twice when the payment is refunded after it was disputed', function () {
        [$driver, $payment, $purchase] = aDriverWhoPaid();
        spend($driver, 20);

        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute($payment, 1999)))->assertOk();
        sendToTheWebhook(stripeEvent('charge.refunded', stripeCharge($payment, 1999, 1999)))->assertOk();

        expect(coinsOf($driver))->toBe(-20)->and($purchase->refresh()->status)->toBe('refunded');
    });

    it('changes nothing when Stripe tells it again', function () {
        [$driver, $payment] = aDriverWhoPaid();
        $opened = stripeEvent('charge.dispute.created', stripeDispute($payment, 1999));
        $won = stripeEvent('charge.dispute.closed', stripeDispute($payment, 1999, 'won'));

        sendToTheWebhook($opened)->assertOk();
        sendToTheWebhook($opened)->assertOk()->assertJson(['result' => 'duplicate']);
        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute($payment, 1999)))->assertOk();
        sendToTheWebhook($won)->assertOk();
        sendToTheWebhook($won)->assertOk()->assertJson(['result' => 'duplicate']);
        sendToTheWebhook(stripeEvent('charge.dispute.closed', stripeDispute($payment, 1999, 'won')))->assertOk();

        expect(coinsOf($driver))->toBe(220)->and(LedgerEntry::query()->where('reason', 'dispute')->count())->toBe(1)->and(LedgerEntry::query()->where('reason', 'dispute_won')->count())->toBe(1);
    });

    it('takes the days of a pass and gives them back when the shop wins', function () {
        $driver = User::factory()->create();
        $attempt = aStripePassAttempt($driver);
        sendToTheWebhook(stripeEvent('checkout.session.completed', stripeSession($attempt)))->assertOk();
        $paidUntil = Wallet::query()->find($driver->id)->infinity_until;

        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute('pi_'.$attempt->id, 1999)))->assertOk();
        expect(Wallet::query()->find($driver->id)->infinity_until->isFuture())->toBeFalse();

        sendToTheWebhook(stripeEvent('charge.dispute.closed', stripeDispute('pi_'.$attempt->id, 1999, 'won')))->assertOk();
        expect(abs($paidUntil->diffInSeconds(Wallet::query()->find($driver->id)->infinity_until, true)))->toBeLessThan(10);
    });

    it('is kept for a person to see when it speaks of a payment the game does not know', function () {
        sendToTheWebhook(stripeEvent('charge.dispute.created', stripeDispute('pi_unknown', 1999)))->assertOk()->assertJson(['result' => 'deferred']);

        expect(PaymentEvent::query()->sole()->state)->toBe('deferred');
    });
});
