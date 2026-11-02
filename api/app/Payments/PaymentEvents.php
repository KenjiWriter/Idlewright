<?php

declare(strict_types=1);

namespace App\Payments;

use App\Games\CurrentGame;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\Purchase;
use App\Services\Purchases;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Works what a provider tells the game, once. Every event is written down first (a provider tells the same event again when it is not sure it arrived), so an event that was
 * worked is not worked again; an event about a payment the game does not have yet (a refund that came before the payment) waits and is worked again later; an error on the
 * game's side is not hidden: the row says it failed, and the provider is told to try again.
 */
final class PaymentEvents
{
    /** How long an event that waits for what it speaks of is worked again; after it the row stays as a record. */
    public const DEFER_DAYS = 3;

    public const DUPLICATE = 'duplicate';

    public function __construct(private readonly PaymentFulfilment $fulfilment, private readonly CurrentGame $games) {}

    /**
     * Works one event and returns what came of it: the state the row is in (see `PaymentEvent`), or `duplicate` for an event that was worked before.
     *
     * @throws Throwable the error of working it, after the row says `failed`
     */
    public function handle(ProviderEvent $event): string
    {
        $row = $this->row($event);

        if (in_array($row->state, [PaymentEvent::STATE_PROCESSED, PaymentEvent::STATE_IGNORED, PaymentEvent::STATE_REJECTED], true)) {
            return self::DUPLICATE;
        }

        return $this->work($row, $event);
    }

    /**
     * Works again the events that were waiting for what they speak of, and returns how many were settled. Called by `payments:reprocess`.
     */
    public function reprocessDeferred(): int
    {
        $settled = 0;

        PaymentEvent::query()->where('state', PaymentEvent::STATE_DEFERRED)->where('received_at', '>=', now()->subDays(self::DEFER_DAYS))->orderBy('id')->each(function (PaymentEvent $row) use (&$settled): void {
            if ($row->data === null) {
                return;
            }

            if ($this->work($row, ProviderEvent::fromArray($row->data)) !== PaymentEvent::STATE_DEFERRED) {
                $settled++;
            }
        });

        return $settled;
    }

    /** The row of an event: the one that is there, or a new one (two deliveries at once make one row). */
    private function row(ProviderEvent $event): PaymentEvent
    {
        $key = ['provider' => $event->provider, 'event_id' => $event->id];

        try {
            return PaymentEvent::query()->firstOrCreate($key, [
                'type' => $event->type,
                'source_type' => $event->sourceType,
                'livemode' => $event->livemode,
                'object_ref' => $event->objectRef(),
                'state' => PaymentEvent::STATE_RECEIVED,
                'data' => $event->toArray(),
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return PaymentEvent::query()->where($key)->firstOrFail();
        }
    }

    private function work(PaymentEvent $row, ProviderEvent $event): string
    {
        $row->tries++;

        try {
            [$state, $note, $gameId] = $this->apply($event);
        } catch (Throwable $e) {
            $row->forceFill(['state' => PaymentEvent::STATE_FAILED, 'note' => mb_substr($e->getMessage(), 0, 250)])->save();

            throw $e;
        }

        $row->forceFill(['state' => $state, 'note' => $note, 'game_id' => $gameId ?? $row->game_id, 'processed_at' => $state === PaymentEvent::STATE_DEFERRED ? null : now()])->save();

        return $state;
    }

    /** @return array{0: string, 1: string|null, 2: int|null} the state of the row, a note, and the game the event turned out to be about */
    private function apply(ProviderEvent $event): array
    {
        return match ($event->type) {
            ProviderEvent::PAID => $this->paid($event),
            ProviderEvent::PENDING => $this->attemptEvent($event, fn () => $this->fulfilment->pending($event->provider, (string) $event->sessionRef)),
            ProviderEvent::FAILED => $this->attemptEvent($event, fn () => $this->fulfilment->closed($event->provider, (string) $event->sessionRef, PaymentAttempt::STATE_FAILED)),
            ProviderEvent::EXPIRED => $this->attemptEvent($event, fn () => $this->fulfilment->closed($event->provider, (string) $event->sessionRef, PaymentAttempt::STATE_EXPIRED)),
            ProviderEvent::REFUNDED, ProviderEvent::DISPUTE_OPENED, ProviderEvent::DISPUTE_CLOSED => $this->aboutPurchase($event),
            default => [PaymentEvent::STATE_IGNORED, 'unknown type', null],
        };
    }

    /** @return array{0: string, 1: string|null, 2: int|null} */
    private function paid(ProviderEvent $event): array
    {
        if ($event->sessionRef === null || $event->amountMinor === null || $event->currency === null) {
            return [PaymentEvent::STATE_IGNORED, 'the event does not say what was paid', null];
        }

        $outcome = $this->fulfilment->paid($event->provider, $event->sessionRef, $event->paymentRef, $event->amountMinor, $event->currency, $event->livemode);

        if (in_array($outcome, [PaymentFulfilment::MISMATCH, PaymentFulfilment::WRONG_MODE], true)) {
            Log::error('A payment was refused.', ['event' => $event->id, 'why' => $outcome]);

            return [PaymentEvent::STATE_REJECTED, $outcome, null];
        }

        return $outcome === PaymentFulfilment::UNKNOWN
            ? [PaymentEvent::STATE_IGNORED, 'a payment the game did not ask for', null]
            : [PaymentEvent::STATE_PROCESSED, $outcome, null];
    }

    /** @return array{0: string, 1: string|null, 2: int|null} */
    private function attemptEvent(ProviderEvent $event, \Closure $do): array
    {
        if ($event->sessionRef === null) {
            return [PaymentEvent::STATE_IGNORED, 'the event does not name the page', null];
        }

        $do();

        return [PaymentEvent::STATE_PROCESSED, null, null];
    }

    /** A refund, or a dispute that was opened or decided: it is about a payment the game paid out. @return array{0: string, 1: string|null, 2: int|null} */
    private function aboutPurchase(ProviderEvent $event): array
    {
        if ($event->paymentRef === null) {
            return [PaymentEvent::STATE_IGNORED, 'the event does not name the payment', null];
        }

        $found = Purchase::query()->withoutGlobalScopes()->where('provider', $event->provider)->where('provider_ref', $event->paymentRef)->first();

        if ($found === null) {
            return [PaymentEvent::STATE_DEFERRED, 'the payment is not there yet', null];
        }

        if ($found->livemode !== $event->livemode) {
            return [PaymentEvent::STATE_REJECTED, 'the other mode', (int) $found->game_id];
        }

        $this->games->within((int) $found->game_id, function () use ($found, $event): void {
            $purchases = app(Purchases::class);
            $purchase = Purchase::query()->findOrFail($found->id);

            match ($event->type) {
                ProviderEvent::REFUNDED => $purchases->applyRefund($purchase, (int) $event->refundedMinor),
                ProviderEvent::DISPUTE_OPENED => $purchases->dispute($purchase),
                default => $purchases->disputeClosed($purchase, $event->won === true),
            };
        });

        return [PaymentEvent::STATE_PROCESSED, null, (int) $found->game_id];
    }
}
