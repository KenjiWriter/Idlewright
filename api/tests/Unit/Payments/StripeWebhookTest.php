<?php

declare(strict_types=1);

use App\Payments\InvalidWebhook;
use App\Payments\ProviderEvent;
use App\Payments\StripeWebhook;

// What arrives from Stripe at the webhook: that it is Stripe's, and what the game takes it to mean. The signature is the one the documentation of Stripe describes
// (HMAC-SHA256 over "<time>.<body>"), and the first test pins it to a value computed with another tool, so that a change of what is signed cannot go unseen.

/** The body of an event, and its signature header made with the same steps as Stripe does. @return array{0: string, 1: string} */
function signedBody(array $event, string $secret = 'whsec_a', int $time = 1_700_000_000): array
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);

    return [$body, "t={$time},v1=".hash_hmac('sha256', "{$time}.{$body}", $secret)];
}

/** @return array<string, mixed> */
function anEventOf(string $type, array $object = [], bool $live = false): array
{
    return ['id' => 'evt_1', 'type' => $type, 'livemode' => $live, 'data' => ['object' => $object]];
}

describe('the signature', function () {
    it('is the HMAC-SHA256 of the time, a dot and the body (a value from another tool)', function () {
        $body = '{"id":"evt_1","type":"x","data":{"object":{}}}';
        $header = 't=1700000000,v1=440c053ffcf1dd637bbbb98ae83816e19a005aa0132858539dfccfec82c3056b';

        expect(StripeWebhook::verify($body, $header, ['whsec_vector'], 300, 1_700_000_100)['id'])->toBe('evt_1');
    });

    it('lets an event signed with the secret in', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'));

        expect(StripeWebhook::verify($body, $header, ['whsec_a'], 300, 1_700_000_010)['type'])->toBe('charge.refunded');
    });

    it('refuses a body that was changed', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'));

        StripeWebhook::verify(str_replace('refunded', 'dispute', $body), $header, ['whsec_a'], 300, 1_700_000_010);
    })->throws(InvalidWebhook::class, 'does not match');

    it('refuses a signature made with another secret', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'), 'whsec_other');

        StripeWebhook::verify($body, $header, ['whsec_a'], 300, 1_700_000_010);
    })->throws(InvalidWebhook::class, 'does not match');

    it('refuses a time that was changed, because the time is signed too', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'));

        StripeWebhook::verify($body, str_replace('t=1700000000', 't=1700000001', $header), ['whsec_a'], 300, 1_700_000_010);
    })->throws(InvalidWebhook::class, 'does not match');

    it('accepts either of two secrets while one is being changed', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'), 'whsec_old');

        expect(StripeWebhook::verify($body, $header, ['whsec_new', 'whsec_old'], 300, 1_700_000_010)['id'])->toBe('evt_1');
    });

    it('accepts a header with two v1 signatures when one of them is right, as when Stripe signs with two secrets', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'));
        $wrongFirst = 't=1700000000,v1='.str_repeat('0', 64).','.substr($header, strpos($header, 'v1='));

        expect(StripeWebhook::verify($body, $wrongFirst, ['whsec_a'], 300, 1_700_000_010)['id'])->toBe('evt_1');
    });

    it('ignores every scheme but v1', function () {
        [$body] = signedBody(anEventOf('charge.refunded'));
        $v0 = 't=1700000000,v0='.hash_hmac('sha256', '1700000000.'.$body, 'whsec_a');

        StripeWebhook::verify($body, $v0, ['whsec_a'], 300, 1_700_000_010);
    })->throws(InvalidWebhook::class, 'no v1');

    it('refuses an event that is older than the tolerance, and one from the future, so a recorded request cannot be played again', function (int $now) {
        [$body, $header] = signedBody(anEventOf('charge.refunded'));

        StripeWebhook::verify($body, $header, ['whsec_a'], 300, $now);
    })->with([1_700_000_301, 1_699_999_699])->throws(InvalidWebhook::class, 'tolerance');

    it('lets in an event right at the edge of the tolerance', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'));

        expect(StripeWebhook::verify($body, $header, ['whsec_a'], 300, 1_700_000_300)['id'])->toBe('evt_1');
    });

    it('does not take a tolerance of nothing for "no check"', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'));

        StripeWebhook::verify($body, $header, ['whsec_a'], 0, 1_700_000_000);
    })->throws(InvalidWebhook::class, 'tolerance');

    it('refuses when there is no secret to check with, and an empty secret is none', function () {
        [$body, $header] = signedBody(anEventOf('charge.refunded'), '');

        StripeWebhook::verify($body, $header, [''], 300, 1_700_000_010);
    })->throws(InvalidWebhook::class, 'no secret');

    it('refuses a request without the header, or with one that is not a signature', function (?string $header) {
        [$body] = signedBody(anEventOf('charge.refunded'));

        StripeWebhook::verify($body, $header, ['whsec_a'], 300, 1_700_000_010);
    })->with([null, '', 'garbage', 't=abc,v1=ff', 'v1=ff'])->throws(InvalidWebhook::class);

    it('refuses a body that is not JSON, or JSON that is not an event, even when it is signed', function (string $body) {
        $header = 't=1700000000,v1='.hash_hmac('sha256', "1700000000.{$body}", 'whsec_a');

        StripeWebhook::verify($body, $header, ['whsec_a'], 300, 1_700_000_010);
    })->with(['not json', '[]', '{"id":"evt_1"}', '{"id":1,"type":"x","data":{"object":{}}}'])->throws(InvalidWebhook::class);
});

describe('the events of Stripe, as the game takes them', function () {
    it('takes a session that is paid for a payment, with what was paid', function () {
        $event = StripeWebhook::normalise(anEventOf('checkout.session.completed', ['id' => 'cs_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'amount_total' => 499, 'currency' => 'pln']));

        expect($event->type)->toBe(ProviderEvent::PAID)
            ->and([$event->sessionRef, $event->paymentRef, $event->amountMinor, $event->currency, $event->livemode])->toBe(['cs_1', 'pi_1', 499, 'PLN', false]);
    });

    it('takes a session that is not paid yet for a payment that is on its way, and a free one for nothing', function () {
        $unpaid = StripeWebhook::normalise(anEventOf('checkout.session.completed', ['id' => 'cs_1', 'payment_status' => 'unpaid']));
        $free = StripeWebhook::normalise(anEventOf('checkout.session.completed', ['id' => 'cs_1', 'payment_status' => 'no_payment_required']));

        expect($unpaid->type)->toBe(ProviderEvent::PENDING)->and($free)->toBeNull();
    });

    it('takes the later answers of a method that confirms later', function () {
        expect(StripeWebhook::normalise(anEventOf('checkout.session.async_payment_succeeded', ['id' => 'cs_1', 'payment_intent' => 'pi_1', 'amount_total' => 499, 'currency' => 'pln']))->type)->toBe(ProviderEvent::PAID)
            ->and(StripeWebhook::normalise(anEventOf('checkout.session.async_payment_failed', ['id' => 'cs_1']))->type)->toBe(ProviderEvent::FAILED)
            ->and(StripeWebhook::normalise(anEventOf('checkout.session.expired', ['id' => 'cs_1']))->type)->toBe(ProviderEvent::EXPIRED);
    });

    it('takes a refund with how much is given back by now, named by the payment', function () {
        $event = StripeWebhook::normalise(anEventOf('charge.refunded', ['id' => 'ch_1', 'payment_intent' => 'pi_1', 'amount' => 1999, 'amount_refunded' => 500, 'currency' => 'pln']));

        expect($event->type)->toBe(ProviderEvent::REFUNDED)
            ->and([$event->paymentRef, $event->amountMinor, $event->refundedMinor])->toBe(['pi_1', 1999, 500]);
    });

    it('takes a dispute that was opened, and one that was decided for or against the shop', function () {
        $opened = StripeWebhook::normalise(anEventOf('charge.dispute.created', ['id' => 'du_1', 'payment_intent' => 'pi_1', 'amount' => 499, 'currency' => 'pln']));
        $won = StripeWebhook::normalise(anEventOf('charge.dispute.closed', ['id' => 'du_1', 'payment_intent' => 'pi_1', 'status' => 'won']));
        $inquiry = StripeWebhook::normalise(anEventOf('charge.dispute.closed', ['id' => 'du_1', 'payment_intent' => 'pi_1', 'status' => 'warning_closed']));
        $lost = StripeWebhook::normalise(anEventOf('charge.dispute.closed', ['id' => 'du_1', 'payment_intent' => 'pi_1', 'status' => 'lost']));
        $open = StripeWebhook::normalise(anEventOf('charge.dispute.closed', ['id' => 'du_1', 'payment_intent' => 'pi_1', 'status' => 'needs_response']));

        expect($opened->type)->toBe(ProviderEvent::DISPUTE_OPENED)
            ->and([$won->type, $won->won])->toBe([ProviderEvent::DISPUTE_CLOSED, true])
            ->and($inquiry->won)->toBeTrue()
            ->and($lost->won)->toBeFalse()
            ->and($open)->toBeNull();
    });

    it('has no use for an event of another kind', function () {
        expect(StripeWebhook::normalise(anEventOf('customer.created', ['id' => 'cus_1'])))->toBeNull();
    });

    it('survives being kept and read back from the row of the event', function () {
        $event = StripeWebhook::normalise(anEventOf('charge.refunded', ['id' => 'ch_1', 'payment_intent' => 'pi_1', 'amount' => 1999, 'amount_refunded' => 500, 'currency' => 'pln']));

        expect(ProviderEvent::fromArray($event->toArray()))->toEqual($event);
    });
});
