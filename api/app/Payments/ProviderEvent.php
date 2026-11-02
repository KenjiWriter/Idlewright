<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Something a payment provider told the game, in the words of the game and without anything that is about the payer: a payment was made (or is on its way, or did not
 * come), a page of payment ran out, a payment was given back, the payer disputed it with their bank and the dispute was decided. Every provider's own events are turned into
 * these, and everything after that is the same for all of them.
 */
final readonly class ProviderEvent
{
    public const PAID = 'payment.paid';

    /** The payer finished the page and the money has not arrived (a method that confirms later). */
    public const PENDING = 'payment.pending';

    public const FAILED = 'payment.failed';

    public const EXPIRED = 'session.expired';

    public const REFUNDED = 'payment.refunded';

    public const DISPUTE_OPENED = 'dispute.opened';

    public const DISPUTE_CLOSED = 'dispute.closed';

    /**
     * @param  string  $id  the provider's id of the event: the same event told twice has the same one
     * @param  string  $type  one of the constants above
     * @param  string  $sourceType  the provider's own name for it, kept for the record
     * @param  string|null  $sessionRef  the page of payment the event is about
     * @param  string|null  $paymentRef  the payment it is about (the same payment is what a refund and a dispute name)
     * @param  int|null  $amountMinor  what was paid, in the smallest unit of the currency
     * @param  string|null  $currency  upper case
     * @param  int|null  $refundedMinor  how much of it is given back by now (a refund)
     * @param  bool|null  $won  whether the shop won the dispute (a dispute that was decided)
     */
    public function __construct(
        public string $provider,
        public string $id,
        public string $type,
        public string $sourceType,
        public bool $livemode,
        public ?string $sessionRef = null,
        public ?string $paymentRef = null,
        public ?int $amountMinor = null,
        public ?string $currency = null,
        public ?int $refundedMinor = null,
        public ?bool $won = null,
    ) {}

    /** The thing the event is about, as the row of the event names it: the page for the events of a page, else the payment. */
    public function objectRef(): ?string
    {
        return $this->sessionRef ?? $this->paymentRef;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'id' => $this->id,
            'type' => $this->type,
            'source_type' => $this->sourceType,
            'livemode' => $this->livemode,
            'session_ref' => $this->sessionRef,
            'payment_ref' => $this->paymentRef,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
            'refunded_minor' => $this->refundedMinor,
            'won' => $this->won,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['provider'],
            (string) $data['id'],
            (string) $data['type'],
            (string) ($data['source_type'] ?? ''),
            (bool) $data['livemode'],
            isset($data['session_ref']) ? (string) $data['session_ref'] : null,
            isset($data['payment_ref']) ? (string) $data['payment_ref'] : null,
            isset($data['amount_minor']) ? (int) $data['amount_minor'] : null,
            isset($data['currency']) ? (string) $data['currency'] : null,
            isset($data['refunded_minor']) ? (int) $data['refunded_minor'] : null,
            isset($data['won']) ? (bool) $data['won'] : null,
        );
    }
}
