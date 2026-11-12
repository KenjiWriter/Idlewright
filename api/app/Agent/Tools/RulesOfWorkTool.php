<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentAnchors;
use App\Agent\AgentContext;
use App\Agent\AgentRules;
use App\Agent\AgentServer;
use App\Games\CurrentGame;
use App\Models\Game;
use Laravel\Mcp\Request;

/**
 * The rules of work and the consent the assistant works under: the first call of every conversation, for the clients of MCP that do not read the instructions of a server.
 */
final class RulesOfWorkTool extends AgentTool
{
    protected string $name = 'zasady';

    protected string $title = 'Rules of work';

    protected string $description = 'Call this first. Returns the rules of work of the assistant, the consent you work under (game, level, until when, on whose behalf), the tools you have now and what is out of your reach (the yardstick of the gates, the world while its editor is rebuilt, what no tool does).';

    public function __construct(private readonly AgentContext $context, private readonly CurrentGame $current, private readonly AgentAnchors $anchors) {}

    protected function run(Request $request): array
    {
        $grant = $this->context->grant();
        $game = Game::query()->find($this->current->id());

        return [
            'rules' => AgentRules::text(),
            'consent' => $grant === null ? null : [
                'game' => (string) $game?->slug,
                'game_name' => (string) $game?->name,
                'level' => $grant->level->value,
                'until' => $grant->expires_at->toIso8601String(),
                'on_behalf_of' => (string) $grant->admin?->email,
            ],
            'tools' => AgentServer::toolNamesFor($this->context->level()),
            'out_of_reach' => $this->anchors->explain(),
            'not_yet' => 'This connection reads, counts, keeps a scratch, submits plans, writes approved plans as drafts and, at level 3, asks for a pack. It never makes a pack live: that is always a person.',
        ];
    }
}
