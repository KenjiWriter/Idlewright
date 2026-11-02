<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Models\Purchase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the purchases that are older than the shop has to keep them. A purchase stays when the account is erased (the books, and the claims that can still be made), with the
 * account cut off, so without this it would stay for ever. It is kept for `ride.premium.payments.retention_years` calendar years after the year it was made in (a purchase of
 * 2026 stays through 2032 and goes in 2033), together with the paid attempt it came from, which holds the consents of the driver. The number of years is a decision of the lawyer
 * and the accountant: this is the tool, not the answer.
 */
final class PrunePurchases extends Command
{
    protected $signature = 'purchases:prune
        {--years= : Calendar years to keep a purchase after the year of it (default from config ride.premium.payments.retention_years)}
        {--dry-run : Only report how many purchases would be removed}';

    protected $description = 'Delete the purchases (and their paid attempts) that are past the years the shop keeps them for';

    public function handle(): int
    {
        $years = (int) ($this->option('years') ?: config('ride.premium.payments.retention_years'));

        if ($years < 1) {
            $this->error('The years are a whole number of at least 1.');

            return self::FAILURE;
        }

        // Every game alike: the books of the shop are the shop's, not a game's.
        $cutoff = now()->startOfYear()->subYears($years);
        $purchases = Purchase::query()->withoutGlobalScopes()->where('created_at', '<', $cutoff);
        $attempts = PaymentAttempt::query()->withoutGlobalScopes()->where('state', PaymentAttempt::STATE_PAID)->where('created_at', '<', $cutoff);
        [$countPurchases, $countAttempts] = [(clone $purchases)->count(), (clone $attempts)->count()];

        if ($this->option('dry-run')) {
            $this->info("{$countPurchases} purchase(s) and {$countAttempts} paid attempt(s) from before {$cutoff->toDateString()} would be removed.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($purchases, $attempts): void {
            $purchases->delete();
            $attempts->delete();
        });

        $this->info("Removed {$countPurchases} purchase(s) and {$countAttempts} paid attempt(s) from before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
