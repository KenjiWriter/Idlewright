<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Games\CurrentGame;
use App\Models\Game;
use Illuminate\Console\Command;

/**
 * Turns the payments of a game on or off. A game that was never told is off in production; this is how somebody says it, once the lawyer has read the texts. Turning them off
 * stops new purchases at once and takes nothing away from what drivers have bought.
 */
final class SwitchPayments extends Command
{
    use RunsInAGame;

    protected $signature = 'payments:switch
        {state : on or off}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Turn the payments with real money of a game on or off';

    public function handle(CurrentGame $current): int
    {
        $state = (string) $this->argument('state');

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('The state is on or off.');

            return self::FAILURE;
        }

        $game = Game::query()->find($current->id());

        if ($game === null) {
            $this->error('There is no such game.');

            return self::FAILURE;
        }

        $game->forceFill(['payments' => [...($game->payments ?? []), 'enabled' => $state === 'on']])->save();

        $this->info("The payments of {$game->slug} are {$state}.");

        if ($state === 'on' && app()->isProduction()) {
            $this->warn('Real money: this is for after the terms of sale and the privacy policy were read by a lawyer.');
        }

        return self::SUCCESS;
    }
}
