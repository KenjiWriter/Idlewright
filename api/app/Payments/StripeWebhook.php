<?php

declare(strict_types=1);

namespace App\Payments;

use JsonException;

/**
 * What arrives from Stripe at the webhook: the check that it is from Stripe, and the turning of its events into the events of the game.
 *
 * The signature is the one Stripe documents: the header `Stripe-Signature: t=<time>,v1=<signature>[,v1=<signature>][,v0=...]`, where a signature is the HMAC-SHA256, with
 * the secret of the endpoint (`whsec_...`) as the key, of `<time>.<the body exactly as it arrived>`. Only the scheme `v1` counts (the other schemes are ignored, so an
 * old scheme cannot be used to get round it), a header can carry several `v1` (Stripe signs with the old secret too for up to a day after it was changed), the comparison does
 * not leak where it differs, and an event older (or newer) than the tolerance is refused, so a recorded request cannot be played again later.
 */
final class StripeWebhook
{
    /**
     * Checks the signature and reads the event.
     *
     * @param  list<string>  $secrets  the secrets that may have signed it; empty ones are not secrets
     * @param  int  $tolerance  how far the time of the signature may be from now, in seconds (never 0)
     * @return array<string, mixed> the event, as Stripe sent it
     *
     * @throws InvalidWebhook
     */
    public static function verify(string $payload, ?string $header, array $secrets, int $tolerance, ?int $now = null): array
    {
        $secrets = array_values(array_filter($secrets, static fn (string $secret): bool => $secret !== ''));

        if ($secrets === []) {
            throw new InvalidWebhook('There is no secret to check the signature with.');
        }

        if ($tolerance < 1) {
            throw new InvalidWebhook('The tolerance of the time of a signature is at least a second.');
        }

        if ($header === null || trim($header) === '') {
            throw new InvalidWebhook('The signature is missing.');
        }

        $time = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            if ($pair[0] === 't' && ctype_digit($pair[1])) {
                $time = (int) $pair[1];
            } elseif ($pair[0] === 'v1' && $pair[1] !== '') {
                $signatures[] = $pair[1];
            }
        }

        if ($time === null || $signatures === []) {
            throw new InvalidWebhook('The signature has no time or no v1 signature.');
        }

        if (abs(($now ?? time()) - $time) > $tolerance) {
            throw new InvalidWebhook('The signature is outside the tolerance of time.');
        }

        $signed = false;

        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $time.'.'.$payload, $secret);

            foreach ($signatures as $signature) {
                $signed = hash_equals($expected, $signature) || $signed;
            }
        }

        if (! $signed) {
            throw new InvalidWebhook('The signature does not match.');
        }

        try {
            $event = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidWebhook('The body is not JSON.');
        }

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null) || ! is_array($event['data']['object'] ?? null)) {
            throw new InvalidWebhook('The body is not an event.');
        }

        return $event;
    }

    /**
     * The event of the game that a Stripe event means, or null for one the game has no use for (Stripe is asked to send only the seven below, so this is a safety, not a
     * way of life).
     *
     * @param  array<string, mixed>  $event
     */
    public static function normalise(array $event): ?ProviderEvent
    {
        $type = (string) $event['type'];
        /** @var array<string, mixed> $object */
        $object = $event['data']['object'];
        $id = (string) $event['id'];
        $live = (bool) ($event['livemode'] ?? false);

        $session = static fn (string $kind): ProviderEvent => new ProviderEvent(
            'stripe', $id, $kind, $type, $live,
            sessionRef: self::text($object['id'] ?? null),
            paymentRef: self::text($object['payment_intent'] ?? null),
            amountMinor: isset($object['amount_total']) ? (int) $object['amount_total'] : null,
            currency: self::currency($object['currency'] ?? null),
        );

        return match ($type) {
            'checkout.session.completed' => match ($object['payment_status'] ?? null) {
                'paid' => $session(ProviderEvent::PAID),
                'unpaid' => $session(ProviderEvent::PENDING),
                default => null,
            },
            'checkout.session.async_payment_succeeded' => $session(ProviderEvent::PAID),
            'checkout.session.async_payment_failed' => $session(ProviderEvent::FAILED),
            'checkout.session.expired' => $session(ProviderEvent::EXPIRED),
            'charge.refunded' => new ProviderEvent(
                'stripe', $id, ProviderEvent::REFUNDED, $type, $live,
                paymentRef: self::text($object['payment_intent'] ?? null),
                amountMinor: isset($object['amount']) ? (int) $object['amount'] : null,
                currency: self::currency($object['currency'] ?? null),
                refundedMinor: isset($object['amount_refunded']) ? (int) $object['amount_refunded'] : null,
            ),
            'charge.dispute.created' => new ProviderEvent(
                'stripe', $id, ProviderEvent::DISPUTE_OPENED, $type, $live,
                paymentRef: self::text($object['payment_intent'] ?? null),
                amountMinor: isset($object['amount']) ? (int) $object['amount'] : null,
                currency: self::currency($object['currency'] ?? null),
            ),
            // A dispute that is closed in favour of the shop, or an inquiry that was closed without becoming one, costs nothing; a lost one costs the money.
            'charge.dispute.closed' => match ($object['status'] ?? null) {
                'won', 'warning_closed' => self::dispute($id, $type, $live, $object, true),
                'lost' => self::dispute($id, $type, $live, $object, false),
                default => null,
            },
            default => null,
        };
    }

    /** @param  array<string, mixed>  $object */
    private static function dispute(string $id, string $type, bool $live, array $object, bool $won): ProviderEvent
    {
        return new ProviderEvent(
            'stripe', $id, ProviderEvent::DISPUTE_CLOSED, $type, $live,
            paymentRef: self::text($object['payment_intent'] ?? null),
            amountMinor: isset($object['amount']) ? (int) $object['amount'] : null,
            currency: self::currency($object['currency'] ?? null),
            won: $won,
        );
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function currency(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? strtoupper($value) : null;
    }
}
