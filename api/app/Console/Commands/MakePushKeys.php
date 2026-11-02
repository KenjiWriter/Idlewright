<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsInAGame;
use App\Games\CurrentGame;
use App\Models\Game;
use App\Push\PushKeys;
use Illuminate\Console\Command;

/**
 * Makes the key pair of a game for web push. Once: the browsers subscribe to the public key, so a new pair makes every subscription of the game useless (`--force` does that and
 * deletes them). The private key is kept encrypted with `APP_KEY`, so a copy of `APP_KEY` is a copy of the keys.
 */
final class MakePushKeys extends Command
{
    use RunsInAGame;

    protected $signature = 'push:keys
        {--force : Replace the keys the game has (every subscription of the game is deleted)}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Make the key pair of a game for web push (VAPID)';

    public function handle(CurrentGame $current): int
    {
        $game = $current->game() ?? Game::query()->findOrFail($current->id());
        $deleted = PushKeys::create($game, (bool) $this->option('force'));

        if ($deleted === null) {
            $this->warn("The game {$game->slug} has its keys already: they are left as they are. Use --force to replace them (every subscription of the game is deleted).");
            $this->line('Public key: '.$game->push_public_key);

            return self::SUCCESS;
        }

        $game->refresh();
        $this->info("The keys of the game {$game->slug} are made".($deleted > 0 ? " and {$deleted} subscriptions of the old ones are deleted" : '').'.');
        $this->line('Public key: '.$game->push_public_key);
        $this->line('Subject: '.$game->push_subject);
        $this->comment('Keep APP_KEY with the backups: the private key is encrypted with it, and without it every driver would have to subscribe again.');

        return self::SUCCESS;
    }
}
