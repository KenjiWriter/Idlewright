<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Games\CurrentGame;
use App\Models\Game;
use App\Services\PushDispatcher;
use App\Services\RunService;
use Illuminate\Console\Command;

/**
 * Tells the drivers who are away what happened to their cars and jobs: it goes through every game that can send push messages, and each game is dealt with as itself (its rules,
 * its keys, its drivers). The scheduler runs it every minute.
 */
final class SendDuePush extends Command
{
    protected $signature = 'push:send-due
        {--game= : Only this game (its slug); by default every game that has keys}';

    protected $description = 'Send the push messages that are due';

    public function handle(CurrentGame $current): int
    {
        $games = Game::query()->whereNotNull('push_public_key')->when($this->option('game'), fn ($query, string $slug) => $query->where('slug', $slug))->get();
        $totals = ['sent' => 0, 'skipped' => 0, 'deferred' => 0];

        foreach ($games as $game) {
            // The services are made inside the game: they hold its rules.
            $report = $current->within($game, fn (): array => app(PushDispatcher::class)->run(RunService::nowMs()));

            foreach ($report as $key => $count) {
                $totals[$key] += $count;
            }
        }

        $this->info("Push: {$totals['sent']} sent, {$totals['deferred']} waiting for their time, {$totals['skipped']} left unsaid.");

        return self::SUCCESS;
    }
}
