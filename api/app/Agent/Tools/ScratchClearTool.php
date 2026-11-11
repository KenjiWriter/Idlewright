<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentScratch;
use App\Agent\ToolRefused;
use Laravel\Mcp\Request;

/** Empties the scratch of the assistant. Nothing of the game changes: the scratch was never saved. */
final class ScratchClearTool extends AgentTool
{
    protected string $name = 'scratch_clear';

    protected string $title = 'Empty the scratch';

    protected string $description = 'Empties the scratch. Nothing of the game changes, as the scratch was never saved.';

    /** The services are made when the tool runs: listing the tools must work in a game that has no live pack yet. */
    public function __construct(private readonly AgentContext $context) {}

    protected function run(Request $request): array
    {
        $grant = $this->context->grant() ?? throw new ToolRefused('The scratch belongs to a consent of the panel; a tool run from the command line has none.');
        app(AgentScratch::class)->clear($grant);

        return ['empty' => true];
    }
}
