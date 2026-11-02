<?php

declare(strict_types=1);

namespace App\Payments;

/** What the provider says a page of payment came to when it is asked (the second way, next to the webhook, to learn that a driver paid). */
final readonly class SessionState
{
    public const OPEN = 'open';

    public const PAID = 'paid';

    /** The page was finished and the money has not arrived. */
    public const PENDING = 'pending';

    public const EXPIRED = 'expired';

    public function __construct(
        public string $ref,
        public string $status,
        public ?string $paymentRef = null,
        public ?int $amountMinor = null,
        public ?string $currency = null,
        public bool $livemode = true,
    ) {}
}
