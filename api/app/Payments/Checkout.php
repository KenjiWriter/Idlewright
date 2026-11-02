<?php

declare(strict_types=1);

namespace App\Payments;

use App\Content\Rules;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\RuleRefusedException;
use App\Games\CurrentGame;
use App\Models\PaymentAttempt;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Entitlements;
use App\Services\Ledger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * A driver chooses a pack or the pass and is sent to pay. The game writes down what the driver was offered (the attempt) and the consents they gave, and only then asks the
 * provider for a page of payment for exactly that; what comes back is checked against the attempt (see `PaymentFulfilment`).
 */
final class Checkout
{
    public function __construct(
        private readonly Payments $payments,
        private readonly Rules $rules,
        private readonly Ledger $ledger,
        private readonly PurchaseLimits $limits,
        private readonly PaymentFulfilment $fulfilment,
        private readonly CurrentGame $games,
    ) {}

    /**
     * Starts a purchase, or returns the attempt that the same `$requestId` started before (nothing new is made, and nothing is asked of the provider again).
     *
     * @param  array<string, mixed>  $consents  by name (see `Payments::consents()`): what the driver confirmed
     * @return array{0: PaymentAttempt, 1: bool} the attempt, and whether it is a new one
     *
     * @throws RuleRefusedException `payments_unavailable`, `account_not_secured`, `purchases_blocked`, `product_not_found`, `infinity_unavailable`, `consent_required`,
     *                              `too_many_open_attempts`, `purchase_limit_reached`
     * @throws IdempotencyConflictException when the request was used for another product
     */
    public function start(User $user, string $product, string $requestId, array $consents): array
    {
        $this->payments->assertAvailable();

        if ($user->isGuest()) {
            throw new RuleRefusedException('account_not_secured');
        }

        $userId = (int) $user->id;
        $same = fn (): ?PaymentAttempt => PaymentAttempt::query()->where('user_id', $userId)->where('request_id', $requestId)->first();

        if (($existing = $same()) !== null) {
            return [$this->sameRequest($existing, $product), false];
        }

        $offer = $this->offer($product);
        $missing = array_values(array_filter($this->payments->consents(), static fn (string $name): bool => ($consents[$name] ?? false) !== true));

        if ($missing !== []) {
            throw new RuleRefusedException('consent_required', ['missing' => implode(',', $missing)]);
        }

        $this->ledger->ensureWallet($userId);
        $provider = $this->payments->provider();

        try {
            $attempt = DB::transaction(function () use ($user, $userId, $product, $requestId, $offer, $provider, $same, $consents): array {
                // The same lock as every posting of the driver: two requests of one driver are one after the other, so the limits cannot be passed by asking twice at once.
                $wallet = $this->ledger->lockWallet($userId);

                if (($existing = $same()) !== null) {
                    return [$this->sameRequest($existing, $product), false];
                }

                $this->guardWallet($wallet);
                $this->limits->check($userId, $offer['amount_minor'], $offer['currency']);

                $now = now();

                return [PaymentAttempt::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'game_id' => $this->games->id(),
                    'user_id' => $userId,
                    'request_id' => $requestId,
                    'product' => $product,
                    'kind' => $offer['kind'],
                    'coins' => $offer['coins'],
                    'grant_days' => $offer['days'],
                    'amount_minor' => $offer['amount_minor'],
                    'currency' => $offer['currency'],
                    'provider' => $provider->key(),
                    'state' => PaymentAttempt::STATE_CREATED,
                    'livemode' => $provider->livemode(),
                    'locale' => $user->locale,
                    'terms_version' => (string) config('ride.legal.version'),
                    'consent_at' => $now,
                    'adult_declared_at' => $now,
                    'waiver_at' => ($consents[Payments::CONSENT_WAIVER] ?? false) === true ? $now : null,
                    'expires_at' => $now->copy()->addMinutes((int) config('ride.premium.payments.attempt_minutes', 35)),
                ]), true];
            }, Ledger::DEADLOCK_ATTEMPTS);
        } catch (UniqueConstraintViolationException) {
            return [$this->sameRequest($same() ?? throw new RuleRefusedException('payments_unavailable'), $product), false];
        }

        [$attempt, $created] = $attempt;

        if ($created) {
            $this->openPage($attempt, $provider, $offer['name']);
        }

        return [$attempt, $created];
    }

    /**
     * What an attempt looks like to the client: where it stands, and, while it is open, where to send the driver.
     *
     * @return array<string, mixed>
     */
    public function view(PaymentAttempt $attempt): array
    {
        $purchase = $attempt->state === PaymentAttempt::STATE_PAID ? Purchase::query()->where('attempt_id', $attempt->id)->first() : null;

        return [
            'attempt' => $attempt->public_id,
            'state' => $attempt->state,
            'product' => $attempt->product,
            'kind' => $attempt->kind,
            'coins' => $attempt->coins,
            'days' => $attempt->grant_days,
            'amount_minor' => $attempt->amount_minor,
            'currency' => $attempt->currency,
            'test_mode' => ! $attempt->livemode,
            'url' => $attempt->isOpen() && $attempt->expires_at->isFuture() ? $attempt->checkout_url : null,
            'expires_at' => $attempt->expires_at->toIso8601String(),
            'purchase' => $purchase === null ? null : ['coins' => $purchase->coins, 'days' => $purchase->grant_days, 'status' => $purchase->status],
        ];
    }

    /**
     * Brings an open attempt up to date by asking the provider, when the webhook has not told the game yet: the driver came back from the page and the game does not know
     * that they paid. It is the second way to learn it, and it makes the same thing happen as the webhook does, once. The provider being out of reach changes nothing.
     */
    public function refresh(PaymentAttempt $attempt): PaymentAttempt
    {
        $after = (int) config('ride.premium.payments.reconcile_after_seconds', 15);

        if (! $attempt->isOpen() || $attempt->provider_session === null || $attempt->created_at->diffInSeconds(now(), true) < $after) {
            return $attempt;
        }

        try {
            $state = $this->payments->provider($attempt->provider)->retrieveSession($attempt->provider_session);
        } catch (Throwable $e) {
            Log::warning('The provider could not be asked about a payment.', ['attempt' => $attempt->public_id, 'error' => $e->getMessage()]);

            return $attempt;
        }

        if ($state === null) {
            return $attempt;
        }

        if ($state->status === SessionState::PAID && $state->amountMinor !== null && $state->currency !== null) {
            $this->fulfilment->paid($attempt->provider, $state->ref, $state->paymentRef, $state->amountMinor, $state->currency, $state->livemode);
        } elseif ($state->status === SessionState::PENDING) {
            $this->fulfilment->pending($attempt->provider, $state->ref);
        } elseif ($state->status === SessionState::EXPIRED) {
            $this->fulfilment->closed($attempt->provider, $state->ref, PaymentAttempt::STATE_EXPIRED);
        }

        return $attempt->refresh();
    }

    /**
     * What is sold under this name: a pack of coins, or the pass.
     *
     * @return array{kind: string, coins: int, days: int|null, amount_minor: int, currency: string, name: string}
     */
    private function offer(string $product): array
    {
        $currency = $this->rules->premiumPriceCurrency();

        if ($product === Entitlements::PASS_PRODUCT) {
            $pass = $this->rules->infinity();

            if (! $this->rules->infinityOn() || $pass === null || $currency === null) {
                throw new RuleRefusedException('infinity_unavailable');
            }

            return ['kind' => PaymentAttempt::KIND_PASS, 'coins' => 0, 'days' => $pass['days'], 'amount_minor' => $pass['price_minor'], 'currency' => $currency, 'name' => __('payments.pass_name')];
        }

        $pack = $this->rules->premiumProduct($product);

        if ($pack === null || $currency === null) {
            throw new RuleRefusedException('product_not_found');
        }

        return ['kind' => PaymentAttempt::KIND_COINS, 'coins' => $pack['coins'], 'days' => null, 'amount_minor' => $pack['price_minor'], 'currency' => $currency, 'name' => $pack['name']];
    }

    private function guardWallet(Wallet $wallet): void
    {
        if ($wallet->purchases_blocked_at !== null) {
            throw new RuleRefusedException('purchases_blocked');
        }
    }

    /** A request that was made before: the same attempt when it is for the same product, else the request id was used for another intention. */
    private function sameRequest(PaymentAttempt $existing, string $product): PaymentAttempt
    {
        if ($existing->product !== $product) {
            throw new IdempotencyConflictException("payment:{$existing->request_id}");
        }

        return $existing;
    }

    /** Asks the provider for the page. When it cannot give one the attempt is over, and the driver is told to try again later. */
    private function openPage(PaymentAttempt $attempt, PaymentProvider $provider, string $name): void
    {
        $web = $this->games->webUrl();

        try {
            $session = $provider->createCheckout($attempt, $name, "{$web}/premium/return?attempt={$attempt->public_id}", "{$web}/premium?attempt={$attempt->public_id}&canceled=1");
        } catch (Throwable $e) {
            report($e);
            $attempt->forceFill(['state' => PaymentAttempt::STATE_FAILED])->save();

            throw new RuleRefusedException('payments_unavailable');
        }

        $attempt->forceFill([
            'provider_session' => $session->ref,
            'checkout_url' => $session->url,
            'expires_at' => $session->expiresAt ?? $attempt->expires_at,
        ])->save();
    }
}
