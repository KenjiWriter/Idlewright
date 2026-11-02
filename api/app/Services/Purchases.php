<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Rules;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\RuleRefusedException;
use App\Models\PaymentAttempt;
use App\Models\Purchase;
use App\Models\Wallet;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The book of the purchases with real money: packs of premium coins, and the pass of the endless refill. A payment provider tells the game that a payment was made (`fulfil`)
 * or taken back (`refund`, `applyRefund`, `dispute`); all of it is safe to be told twice, because providers retry. The coins move in the same transaction as the record, through
 * the ledger.
 *
 * A payment that came through a page of payment (a `PaymentAttempt`) is paid out as the attempt says: the coins, the days and the price the driver was OFFERED, not what the
 * rules say by now. A purchase without an attempt (the tests, the people who run the game) is paid out as the rules say.
 */
final class Purchases
{
    public function __construct(private readonly Ledger $ledger, private readonly Rules $rules, private readonly Entitlements $entitlements) {}

    /**
     * Records a paid purchase of a pack and gives the driver its coins. The price that is recorded is the one of the catalog now, unless the provider says what
     * was charged (`$amountMinor`, `$currency`) or an attempt says what was offered: the record is what was paid, and the catalog may change later.
     *
     * Told again with the same payment, it returns the purchase that is there and gives nothing more.
     *
     * @throws RuleRefusedException `product_not_found` when the rules do not sell the pack (an attempt carries its own coins, so it needs no rule)
     * @throws IdempotencyConflictException when the payment was recorded for another driver or another pack
     */
    public function fulfil(int $userId, string $product, string $provider, string $providerRef, ?int $amountMinor = null, ?string $currency = null, ?PaymentAttempt $attempt = null): Purchase
    {
        if ($attempt === null) {
            $pack = $this->rules->premiumProduct($product) ?? throw new RuleRefusedException('product_not_found');
            [$coins, $price, $priceCurrency] = [$pack['coins'], $pack['price_minor'], (string) $this->rules->premiumPriceCurrency()];
        } else {
            [$coins, $price, $priceCurrency] = [$attempt->coins, $attempt->amount_minor, $attempt->currency];
        }

        $this->ledger->ensureWallet($userId);

        return DB::transaction(function () use ($userId, $product, $provider, $providerRef, $amountMinor, $currency, $attempt, $coins, $price, $priceCurrency): Purchase {
            // The same lock as every posting of the driver, so two reports of one payment cannot both get past the check below.
            $this->ledger->lockWallet($userId);

            $existing = $this->recorded($provider, $providerRef);

            if ($existing !== null) {
                if ($existing->user_id !== $userId || $existing->product !== $product) {
                    throw new IdempotencyConflictException("{$provider}:{$providerRef}");
                }

                return $existing;
            }

            $purchase = Purchase::query()->create([
                'user_id' => $userId,
                'product' => $product,
                'coins' => $coins,
                'amount_minor' => $amountMinor ?? $price,
                'currency' => $currency ?? $priceCurrency,
                'provider' => $provider,
                'provider_ref' => $providerRef,
                'status' => Purchase::STATUS_PAID,
                ...$this->fromAttempt($attempt),
            ]);

            $this->ledger->postPremium($userId, $coins, 'purchase', self::key('purchase', $provider, $providerRef));

            return $purchase;
        }, Ledger::DEADLOCK_ATTEMPTS);
    }

    /**
     * Records a pass of the endless refill that was paid for (or given by the people who run the game: then the amount is 0) and adds its days to the end of the
     * pass the driver has. There are no coins in it: the record has `coins` 0 and the product `infinity`, and it keeps the days it gave, so that a refund takes back those and no
     * others. The cars of the driver are settled first, so the pass counts from now on.
     *
     * Told again with the same payment, it returns the purchase that is there and gives nothing more.
     *
     * @param  int|null  $days  how long it lasts; the days of the attempt, else the days of the rules, when left out
     *
     * @throws RuleRefusedException `infinity_unavailable` when the rules do not sell the pass or have switched it off (an attempt carries its own days, so it needs no rule)
     * @throws IdempotencyConflictException when the payment was recorded for another driver or another product
     */
    public function fulfilPass(int $userId, string $provider, string $providerRef, ?int $days = null, ?int $amountMinor = null, ?string $currency = null, ?PaymentAttempt $attempt = null): Purchase
    {
        if ($attempt === null) {
            $pass = $this->rules->infinity();

            if (! $this->rules->infinityOn() || $pass === null) {
                throw new RuleRefusedException('infinity_unavailable');
            }

            [$grant, $price, $priceCurrency] = [$days ?? $pass['days'], $pass['price_minor'], (string) $this->rules->premiumPriceCurrency()];
        } else {
            [$grant, $price, $priceCurrency] = [$days ?? (int) $attempt->grant_days, $attempt->amount_minor, $attempt->currency];
        }

        $this->ledger->ensureWallet($userId);
        $this->entitlements->settleRuns($userId);

        return DB::transaction(function () use ($userId, $provider, $providerRef, $amountMinor, $currency, $attempt, $grant, $price, $priceCurrency): Purchase {
            $wallet = $this->ledger->lockWallet($userId);

            $existing = $this->recorded($provider, $providerRef);

            if ($existing !== null) {
                if ($existing->user_id !== $userId || $existing->product !== Entitlements::PASS_PRODUCT) {
                    throw new IdempotencyConflictException("{$provider}:{$providerRef}");
                }

                return $existing;
            }

            $purchase = Purchase::query()->create([
                'user_id' => $userId,
                'product' => Entitlements::PASS_PRODUCT,
                'coins' => 0,
                'grant_days' => $grant,
                'amount_minor' => $amountMinor ?? $price,
                'currency' => $currency ?? $priceCurrency,
                'provider' => $provider,
                'provider_ref' => $providerRef,
                'status' => Purchase::STATUS_PAID,
                ...$this->fromAttempt($attempt),
            ]);

            $this->entitlements->extendInfinity($wallet, $grant);

            return $purchase;
        }, Ledger::DEADLOCK_ATTEMPTS);
    }

    /**
     * Records a payment for an account that was erased after the driver was sent to pay: the money is in the books of the shop and there is nobody to give the coins to, so
     * nothing is given, the purchase has no account, and it is marked for a person to give the money back. Told again, it returns the record that is there.
     */
    public function recordOrphan(PaymentAttempt $attempt, string $provider, string $providerRef): Purchase
    {
        $existing = $this->recorded($provider, $providerRef);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return Purchase::query()->create([
                'user_id' => null,
                'product' => $attempt->product,
                'coins' => $attempt->coins,
                'grant_days' => $attempt->grant_days,
                'amount_minor' => $attempt->amount_minor,
                'currency' => $attempt->currency,
                'provider' => $provider,
                'provider_ref' => $providerRef,
                'status' => Purchase::STATUS_PAID,
                'review_reason' => Purchase::REVIEW_ACCOUNT_ERASED,
                ...$this->fromAttempt($attempt),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->recorded($provider, $providerRef) ?? throw new IdempotencyConflictException("{$provider}:{$providerRef}");
        }
    }

    /**
     * Records that a payment was taken back whole (a refund) and takes what it gave away: the coins (when the driver has spent them, the balance goes below zero: they owe the
     * difference, and the next purchase pays it off), or the days of the pass it gave. Told again, it changes nothing.
     */
    public function refund(Purchase $purchase): Purchase
    {
        return $this->takeBack($purchase, function (Purchase $locked, ?Wallet $wallet): void {
            // What a dispute took away is not taken twice.
            if ($locked->status !== Purchase::STATUS_DISPUTED && $wallet !== null) {
                $this->remove($locked, $wallet, 'refund');
            }
        }, fn (Purchase $locked): bool => $locked->status === Purchase::STATUS_REFUNDED, function (Purchase $locked): void {
            $locked->forceFill([
                'status' => Purchase::STATUS_REFUNDED,
                'refunded_at' => now(),
                'refunded_amount_minor' => $locked->amount_minor,
                'review_reason' => null,
            ])->save();
        });
    }

    /**
     * A payment was given back, in whole or in part (`$refundedMinor` is all that has been given back by now, as providers tell it). A whole refund is `refund()`. A part is
     * recorded and left for a person: the coins stay the driver's, and the purchase is marked to be looked at. Told again, or told a smaller number than before, it changes nothing.
     */
    public function applyRefund(Purchase $purchase, int $refundedMinor): Purchase
    {
        if ($refundedMinor >= $purchase->amount_minor) {
            return $this->refund($purchase);
        }

        if ($refundedMinor <= $purchase->refunded_amount_minor || ! in_array($purchase->status, Purchase::KEPT_STATUSES, true)) {
            return $purchase;
        }

        $purchase->forceFill(['status' => Purchase::STATUS_PARTIALLY_REFUNDED, 'refunded_amount_minor' => $refundedMinor, 'review_reason' => Purchase::REVIEW_PARTIAL_REFUND])->save();

        return $purchase;
    }

    /**
     * The payer disputed the payment with their bank. What it gave is taken away at once (the same way as a refund: a driver who spent the coins owes them), the purchase is
     * `disputed`, and the driver cannot buy until it is decided. Told again, it changes nothing.
     */
    public function dispute(Purchase $purchase): Purchase
    {
        return $this->takeBack($purchase, function (Purchase $locked, ?Wallet $wallet): void {
            if ($wallet !== null) {
                $this->remove($locked, $wallet, 'dispute');
                $wallet->forceFill(['purchases_blocked_at' => $wallet->purchases_blocked_at ?? now()])->save();
            }
        }, fn (Purchase $locked): bool => $locked->dispute_state !== null || ! in_array($locked->status, Purchase::KEPT_STATUSES, true), function (Purchase $locked): void {
            $locked->forceFill(['status' => Purchase::STATUS_DISPUTED, 'dispute_state' => Purchase::DISPUTE_OPEN])->save();
        });
    }

    /**
     * The dispute was decided. When the shop won, what was taken is given back and the block is lifted (unless another dispute is open or was lost); when it lost, the
     * money is gone, the purchase counts as given back, and the block stays for a person to lift. Told again, it changes nothing.
     */
    public function disputeClosed(Purchase $purchase, bool $won): Purchase
    {
        return $this->takeBack($purchase, function (Purchase $locked, ?Wallet $wallet) use ($won): void {
            if ($wallet === null || ! $won) {
                return;
            }

            if ($locked->product === Entitlements::PASS_PRODUCT) {
                $this->entitlements->extendInfinity($wallet, (int) $locked->grant_days);
            } elseif ($locked->coins > 0) {
                $this->ledger->postPremium((int) $locked->user_id, $locked->coins, 'dispute_won', self::key('disputewon', $locked->provider, $locked->provider_ref));
            }

            $others = Purchase::query()->where('user_id', $locked->user_id)->whereKeyNot($locked->id)->whereIn('dispute_state', [Purchase::DISPUTE_OPEN, Purchase::DISPUTE_LOST])->exists();

            if (! $others) {
                $wallet->forceFill(['purchases_blocked_at' => null])->save();
            }
        }, fn (Purchase $locked): bool => $locked->status !== Purchase::STATUS_DISPUTED || $locked->dispute_state !== Purchase::DISPUTE_OPEN, function (Purchase $locked) use ($won): void {
            $locked->forceFill($won
                ? ['status' => Purchase::STATUS_PAID, 'dispute_state' => Purchase::DISPUTE_WON]
                : ['status' => Purchase::STATUS_REFUNDED, 'dispute_state' => Purchase::DISPUTE_LOST, 'refunded_at' => now(), 'refunded_amount_minor' => $locked->amount_minor])->save();
        });
    }

    /**
     * The shape of everything that changes a purchase after the money moved: the wallet of the driver is locked (when the purchase still has one), the purchase is read again
     * under the lock, and when nothing is to be done (`$settled`) it is returned as it is; else `$move` moves the coins or days and `$mark` changes the record.
     *
     * @param  \Closure(Purchase, ?Wallet): void  $move
     * @param  \Closure(Purchase): bool  $settled
     * @param  \Closure(Purchase): void  $mark
     */
    private function takeBack(Purchase $purchase, \Closure $move, \Closure $settled, \Closure $mark): Purchase
    {
        if ($purchase->user_id === null) {
            // The account was erased: the record stays, and there are no coins left to move.
            $purchase->refresh();

            if (! $settled($purchase)) {
                $move($purchase, null);
                $mark($purchase);
            }

            return $purchase;
        }

        $userId = (int) $purchase->user_id;
        $this->ledger->ensureWallet($userId);

        return DB::transaction(function () use ($purchase, $userId, $move, $settled, $mark): Purchase {
            $wallet = $this->ledger->lockWallet($userId);
            $purchase->refresh();

            if ($settled($purchase)) {
                return $purchase;
            }

            $move($purchase, $wallet);
            $mark($purchase);

            return $purchase;
        }, Ledger::DEADLOCK_ATTEMPTS);
    }

    /** Takes away what a purchase gave: its coins (the balance may go below zero), or the days of its pass (a pass bought before the days were recorded ends at once). */
    private function remove(Purchase $purchase, Wallet $wallet, string $reason): void
    {
        if ($purchase->product === Entitlements::PASS_PRODUCT) {
            $purchase->grant_days === null ? $this->entitlements->endInfinity($wallet) : $this->entitlements->shortenInfinity($wallet, $purchase->grant_days);

            return;
        }

        $this->ledger->postPremium((int) $purchase->user_id, -$purchase->coins, $reason, self::key($reason, $purchase->provider, $purchase->provider_ref), allowDebt: true);
    }

    private function recorded(string $provider, string $providerRef): ?Purchase
    {
        return Purchase::query()->where('provider', $provider)->where('provider_ref', $providerRef)->first();
    }

    /**
     * What a purchase takes from the attempt it came from: the game, the page of payment, the mode, and the consents the driver gave.
     *
     * @return array<string, mixed>
     */
    private function fromAttempt(?PaymentAttempt $attempt): array
    {
        if ($attempt === null) {
            return [];
        }

        return [
            'game_id' => $attempt->game_id,
            'attempt_id' => $attempt->id,
            'provider_session' => $attempt->provider_session,
            'livemode' => $attempt->livemode,
            'terms_version' => $attempt->terms_version,
            'consent_at' => $attempt->consent_at,
            'waiver_at' => $attempt->waiver_at,
            'adult_declared_at' => $attempt->adult_declared_at,
        ];
    }

    /** The key of a posting for a payment: short enough for the ledger, and the same for the same payment. */
    private static function key(string $kind, string $provider, string $providerRef): string
    {
        $key = "{$kind}:{$provider}:{$providerRef}";

        return strlen($key) <= Ledger::MAX_KEY_LENGTH ? $key : "{$kind}:".hash('sha256', "{$provider}:{$providerRef}");
    }
}
