<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use Illuminate\Console\Command;

/**
 * Removes what the payments leave behind and does not need to keep: the attempts that nothing was paid on (a page of payment that nobody finished says nothing about money),
 * after a month, and the events of the provider that were worked, after thirteen months (a refund or a dispute can come a year after the payment). The purchases, and the paid
 * attempts that hold the consents, are `purchases:prune`'s.
 */
final class PrunePayments extends Command
{
    /** How long an attempt that nothing was paid on is kept: long enough to answer a driver who asks what happened. */
    public const ATTEMPT_DAYS = 30;

    /** How long a worked event is kept. */
    public const EVENT_MONTHS = 13;

    protected $signature = 'payments:prune {--dry-run : Only report how many rows would be removed}';

    protected $description = 'Delete the attempts to pay that nothing was paid on, and the old events of the payment providers';

    public function handle(): int
    {
        // A mismatch stays: a person has to look at it.
        $attempts = PaymentAttempt::query()->withoutGlobalScopes()->whereIn('state', [PaymentAttempt::STATE_FAILED, PaymentAttempt::STATE_EXPIRED])->where('created_at', '<', now()->subDays(self::ATTEMPT_DAYS));
        // An attempt that is still open and ran out long ago is over too: the page of the provider does not work after its time.
        $ranOut = PaymentAttempt::query()->withoutGlobalScopes()->whereIn('state', PaymentAttempt::OPEN_STATES)->where('expires_at', '<', now()->subDays(self::ATTEMPT_DAYS));
        $events = PaymentEvent::query()->whereIn('state', [PaymentEvent::STATE_PROCESSED, PaymentEvent::STATE_IGNORED, PaymentEvent::STATE_DEFERRED])
            ->where('received_at', '<', now()->subMonths(self::EVENT_MONTHS));

        $counts = [(clone $attempts)->count() + (clone $ranOut)->count(), (clone $events)->count()];

        if ($this->option('dry-run')) {
            $this->info("{$counts[0]} attempt(s) and {$counts[1]} event(s) would be removed.");

            return self::SUCCESS;
        }

        $attempts->delete();
        $ranOut->delete();
        $events->delete();

        $this->info("Removed {$counts[0]} attempt(s) and {$counts[1]} event(s).");

        return self::SUCCESS;
    }
}
