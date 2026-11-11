<?php

declare(strict_types=1);

namespace App\Agent\Tools;

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

    protected string $description = 'Call this first. Returns the rules of work of the assistant, the consent you work under (game, level, until when, on whose behalf) and the tools you have now.';

    public function __construct(private readonly AgentContext $context, private readonly CurrentGame $current) {}

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
            'not_yet' => 'This version of the connection reads only. Simulations, plans approved in the panel and writing drafts come in the next stages.',
        ];
    }
}
