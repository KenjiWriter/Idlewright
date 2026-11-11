<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Content\Bridge\BriefBuilder;
use App\Games\CurrentGame;
use App\Models\ContentActivation;
use App\Models\ContentDefinition;
use App\Models\Game;
use App\Services\ContentPacks;
use App\Services\PendingChanges;
use Laravel\Mcp\Request;

/**
 * The game the assistant works in, at a glance: the pack the players have, how many drafts wait, how many cars and parts there are, and the sections of the rules with how
 * many numbers each has (what `rules_read` reads).
 */
final class GameOverviewTool extends AgentTool
{
    protected string $name = 'game_overview';

    protected string $title = 'Game overview';

    protected string $description = 'The game you work in: the live pack, the drafts that wait, how many cars and parts there are, and the sections of the rules with how many numbers each has (read them with rules_read).';

    public function __construct(
        private readonly CurrentGame $current,
        private readonly ContentPacks $packs,
        private readonly PendingChanges $pending,
        private readonly BriefBuilder $briefs,
    ) {}

    protected function run(Request $request): array
    {
        $game = Game::query()->find($this->current->id());
        $live = $this->packs->active();
        $count = static fn (string $kind): int => ContentDefinition::query()->where('kind', $kind)->where('status', ContentDefinition::STATUS_ACTIVE)->count();

        return [
            'game' => ['slug' => (string) $game?->slug, 'name' => (string) $game?->name],
            'live_pack' => $live === null ? null : ['number' => $live->number, 'since' => ContentActivation::query()->latest('id')->first()?->created_at?->toIso8601String()],
            'drafts_waiting' => $this->pending->waiting(),
            'cars' => $count(ContentDefinition::KIND_VEHICLE),
            'parts' => $count(ContentDefinition::KIND_PART),
            'rules_sections' => $this->briefs->sections(),
            'languages' => ['pl', 'en', 'fr', 'de', 'pt-BR'],
        ];
    }

    protected function summary(Request $request, array $data): string
    {
        return 'overview of '.($data['game']['slug'] ?? '?');
    }
}
