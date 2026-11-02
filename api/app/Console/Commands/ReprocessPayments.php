<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Payments\PaymentEvents;
use Illuminate\Console\Command;

/**
 * Works again the events of the payment provider that came before what they speak of (a refund before the payment it takes back): the provider does not promise to tell things
 * in order. An event is tried again for three days; what is still not settled after that stays as a record for a person to look at.
 */
final class ReprocessPayments extends Command
{
    protected $signature = 'payments:reprocess';

    protected $description = 'Work again the events of the payment providers that were waiting for a payment';

    public function handle(PaymentEvents $events): int
    {
        $settled = $events->reprocessDeferred();

        $this->info("{$settled} waiting event(s) settled.");

        return self::SUCCESS;
    }
}
