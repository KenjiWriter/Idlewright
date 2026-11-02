<?php

declare(strict_types=1);

namespace App\Payments;

use App\Exceptions\RuleRefusedException;
use App\Models\PaymentAttempt;
use App\Models\Purchase;

/**
 * How much a driver may buy and have waiting to be paid. A card that is not the driver's, a child, and a driver who buys, spends and takes the money back are what the limits
 * are for: they cost little to ask and keep the loss small while a person looks. What counts is the money the shop kept (less what was given back) in the last 24 hours and
 * the last 30 days, plus the pages of payment that are open now, in the currency of the purchase.
 */
final class PurchaseLimits
{
    public function __construct(private readonly Payments $payments) {}

    /**
     * @throws RuleRefusedException `too_many_open_attempts`, or `purchase_limit_reached` (with `available`: what the driver can still spend now)
     */
    public function check(int $userId, int $amountMinor, string $currency): void
    {
        $limits = $this->payments->limits();
        $open = PaymentAttempt::query()->where('user_id', $userId)->whereIn('state', PaymentAttempt::OPEN_STATES)->where('expires_at', '>', now());

        if ($open->count() >= $limits['max_open_attempts']) {
            throw new RuleRefusedException('too_many_open_attempts');
        }

        $waiting = (int) $open->clone()->where('currency', $currency)->sum('amount_minor');
        $room = min(
            $limits['per_day_minor'] - $this->kept($userId, $currency, now()->subDay()) - $waiting,
            $limits['per_month_minor'] - $this->kept($userId, $currency, now()->subDays(30)) - $waiting,
        );

        if ($amountMinor > $room) {
            throw new RuleRefusedException('purchase_limit_reached', ['available' => self::money(max(0, $room), $currency)]);
        }
    }

    /** What the shop kept of a driver's payments since a moment, in one currency: what is disputed counts too, until it is decided in the driver's favour. */
    private function kept(int $userId, string $currency, \DateTimeInterface $since): int
    {
        return (int) Purchase::query()->where('user_id', $userId)->where('currency', $currency)->where('created_at', '>=', $since)
            ->whereIn('status', [...Purchase::KEPT_STATUSES, Purchase::STATUS_DISPUTED])
            ->selectRaw('coalesce(sum(amount_minor - refunded_amount_minor), 0) as kept')->value('kept');
    }

    /** An amount for a message: `12.34 PLN`. */
    public static function money(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2, '.', '').' '.$currency;
    }
}
