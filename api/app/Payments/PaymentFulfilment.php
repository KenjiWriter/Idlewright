<?php

declare(strict_types=1);

namespace App\Payments;

use App\Games\CurrentGame;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Purchases;
use Illuminate\Support\Facades\Log;

/**
 * What happens to an attempt when the provider says what became of it. The webhook and the driver coming back from the page (which asks the provider when the webhook is late)
 * both end here, so a payment is paid out once however many ways tell the game about it, in whatever order and at once.
 *
 * What is paid out is what the attempt says the driver was offered, not what the rules say by now; the provider's word about the amount and the currency is checked against it.
 */
final class PaymentFulfilment
{
    /** The coins or the pass were given (now, or they had been before: the answer is the same). */
    public const FULFILLED = 'fulfilled';

    public const ALREADY = 'already';

    /** The account was erased between the page and the payment: the money is in the books, nothing was given, and a person gives it back. */
    public const ORPHAN = 'orphan';

    /** The amount or the currency is not what was offered: nothing was given, and a person looks at it. */
    public const MISMATCH = 'mismatch';

    /** The page of payment is not one of this game's: a payment the game never asked for. */
    public const UNKNOWN = 'unknown_session';

    /** The payment was made in the other mode (test money for a live attempt, or the other way). */
    public const WRONG_MODE = 'wrong_mode';

    public function __construct(private readonly CurrentGame $games) {}

    /** The money arrived (or the provider says the page was paid). Returns one of the constants above. */
    public function paid(string $provider, string $sessionRef, ?string $paymentRef, int $amountMinor, string $currency, bool $livemode): string
    {
        $found = $this->find($provider, $sessionRef);

        if ($found === null) {
            return self::UNKNOWN;
        }

        return $this->games->within((int) $found->game_id, function () use ($found, $provider, $sessionRef, $paymentRef, $amountMinor, $currency, $livemode): string {
            // The services are asked for here, after the game of the attempt is in force: they read its rules.
            $purchases = app(Purchases::class);
            $attempt = PaymentAttempt::query()->findOrFail($found->id);

            if ($attempt->livemode !== $livemode) {
                Log::error('A payment was made in the other mode than the attempt.', ['attempt' => $attempt->public_id, 'live' => $livemode]);

                return self::WRONG_MODE;
            }

            if ($attempt->state === PaymentAttempt::STATE_PAID) {
                return self::ALREADY;
            }

            if ($attempt->state === PaymentAttempt::STATE_MISMATCH) {
                return self::MISMATCH;
            }

            if ($attempt->amount_minor !== $amountMinor || $attempt->currency !== strtoupper($currency)) {
                $attempt->forceFill(['state' => PaymentAttempt::STATE_MISMATCH, 'provider_ref' => $paymentRef])->save();
                Log::error('A payment is not what the attempt offered.', ['attempt' => $attempt->public_id, 'paid' => $amountMinor.' '.$currency, 'offered' => $attempt->amount_minor.' '.$attempt->currency]);

                return self::MISMATCH;
            }

            // A payment names itself; a provider that does not gets the page's name for it.
            $reference = $paymentRef ?? $sessionRef;
            $user = $attempt->user_id === null ? null : User::query()->find($attempt->user_id);

            if ($user === null) {
                $purchases->recordOrphan($attempt, $provider, $reference);
                $outcome = self::ORPHAN;
            } elseif ($attempt->kind === PaymentAttempt::KIND_PASS) {
                $purchases->fulfilPass((int) $user->id, $provider, $reference, attempt: $attempt);
                $outcome = self::FULFILLED;
            } else {
                $purchases->fulfil((int) $user->id, $attempt->product, $provider, $reference, attempt: $attempt);
                $outcome = self::FULFILLED;
            }

            $attempt->forceFill(['state' => PaymentAttempt::STATE_PAID, 'provider_ref' => $reference, 'completed_at' => now()])->save();

            return $outcome;
        });
    }

    /** The payer finished the page and the money has not arrived: the attempt waits for it. */
    public function pending(string $provider, string $sessionRef): void
    {
        $this->close($provider, $sessionRef, PaymentAttempt::STATE_PROCESSING, [PaymentAttempt::STATE_CREATED]);
    }

    /** The attempt is over without a payment (`failed` or `expired`); an attempt that was paid is not touched. */
    public function closed(string $provider, string $sessionRef, string $state): void
    {
        $this->close($provider, $sessionRef, $state, PaymentAttempt::OPEN_STATES);
    }

    /** @param  list<string>  $from  the states the attempt may be in for it to move */
    private function close(string $provider, string $sessionRef, string $state, array $from): void
    {
        $attempt = $this->find($provider, $sessionRef);

        if ($attempt !== null && in_array($attempt->state, $from, true)) {
            $attempt->forceFill(['state' => $state])->save();
        }
    }

    /** The attempt a page of payment belongs to, in whatever game it was made. */
    private function find(string $provider, string $sessionRef): ?PaymentAttempt
    {
        return PaymentAttempt::query()->withoutGlobalScopes()->where('provider', $provider)->where('provider_session', $sessionRef)->first();
    }
}
