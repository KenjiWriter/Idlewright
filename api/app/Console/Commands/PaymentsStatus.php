<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Games\CurrentGame;
use App\Models\Game;
use App\Payments\Payments;
use App\Payments\PaymentsUnavailable;
use App\Payments\StripeProvider;
use Illuminate\Console\Command;

/**
 * Says where the payments stand: the provider that is named, whether it is ready and in which mode, and, for each game, whether its payments are turned on and whether a driver
 * can be sent to pay. It changes nothing and shows no key. It is what to run on a server before and after anything about the payments is changed.
 */
final class PaymentsStatus extends Command
{
    protected $signature = 'payments:status';

    protected $description = 'Show the payment provider, its mode, and the games that take real money';

    public function handle(Payments $payments, CurrentGame $current): int
    {
        $key = $payments->providerKey();
        $this->line('Provider: '.($key ?? 'none (nothing is sold)'));

        if ($key === 'stripe') {
            $stripe = app(StripeProvider::class);

            try {
                $this->line('Mode of the key: '.($stripe->livemode() ? 'LIVE money' : 'test money').($stripe->modeAllowed() ? '' : '  <-- not allowed in this installation (environment: '.app()->environment().')'));
            } catch (PaymentsUnavailable $e) {
                $this->warn($e->getMessage());
            }

            $this->line('Webhook secret: '.(filled(config('ride.premium.payments.stripe.webhook_secret')) ? 'set' : 'MISSING'));
            $this->line('Ready to send a driver to pay: '.($stripe->ready() ? 'yes' : 'no'));
        } elseif ($key === 'fake') {
            $this->line('The fake provider takes no money'.(app()->isProduction() ? '  <-- refused in production' : '.'));
        }

        $rows = [];

        foreach (Game::query()->orderBy('id')->get() as $game) {
            $rows[] = $current->within($game, fn (): array => [
                $game->slug,
                is_bool($game->payments['enabled'] ?? null) ? ($game->payments['enabled'] ? 'on' : 'off') : (app()->isProduction() ? 'off (not said)' : 'on (not said)'),
                $payments->withdrawalMode(),
                $payments->available() ? 'yes' : 'no',
            ]);
        }

        $this->table(['Game', 'Payments', 'Withdrawal', 'Can sell now'], $rows);

        return self::SUCCESS;
    }
}
