<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Game;
use App\Models\PushSubscription;
use App\Push\PushMessage;
use App\Push\PushOutcome;
use App\Push\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one push message to one device. The push services answer in their own time and sometimes not at all, so it is not done while a driver waits for a page: it is tried
 * three times, a minute, five and fifteen apart, and a service that says to wait is waited for.
 */
class SendPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @param  array{kind: string, title: string, body: string, url: string, tag: string, ttl: int, urgency: string, lang: string, nid?: string|null}  $message */
    public function __construct(public readonly int $subscriptionId, public readonly array $message) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(PushSender $sender): void
    {
        $subscription = PushSubscription::query()->withoutGlobalScopes()->find($this->subscriptionId);
        $game = $subscription === null ? null : Game::query()->find($subscription->game_id);

        if ($subscription === null || $game === null || ! $subscription->isActive()) {
            return;
        }

        $result = $sender->send($game, $subscription, PushMessage::fromArray($this->message));

        match ($result['outcome']) {
            PushOutcome::Sent => $subscription->forceFill(['last_ok_at' => now(), 'failures' => 0])->save(),
            // The push service says the device is gone: nothing is kept of it.
            PushOutcome::Gone => $subscription->delete(),
            PushOutcome::Retry => $this->release($result['retry_after']),
            PushOutcome::Rejected => $this->countFailure($subscription),
        };
    }

    /** The queue gave up on the message (every try came to nothing): that counts against the subscription like a refusal. */
    public function failed(Throwable $problem): void
    {
        $subscription = PushSubscription::query()->withoutGlobalScopes()->find($this->subscriptionId);

        if ($subscription !== null) {
            $this->countFailure($subscription);
        }
    }

    /** A subscription that fails over and over is switched off: it stays out of the way until the device subscribes again. */
    private function countFailure(PushSubscription $subscription): void
    {
        $failures = $subscription->failures + 1;

        $subscription->forceFill($failures >= PushSubscription::MAX_FAILURES ? ['failures' => $failures, 'disabled_at' => now()] : ['failures' => $failures])->save();
    }
}
