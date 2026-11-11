<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentScratch;
use App\Agent\ToolRefused;
use Laravel\Mcp\Request;

/** The scratch of the assistant as it stands, checked again on the content as it is now (the drafts under it may have changed since it was set). */
final class ScratchReadTool extends AgentTool
{
    protected string $name = 'scratch_read';

    protected string $title = 'Read the scratch';

    protected string $description = 'The scratch as it stands: its summary and operations, checked again on the content as it is now (every number it changes, or what no longer holds because someone changed the drafts under it).';

    /** The services are made when the tool runs: listing the tools must work in a game that has no live pack yet. */
    public function __construct(private readonly AgentContext $context) {}

    protected function run(Request $request): array
    {
        $grant = $this->context->grant() ?? throw new ToolRefused('The scratch belongs to a consent of the panel; a tool run from the command line has none.');
        $check = app(AgentScratch::class)->current($grant);

        if ($check === null) {
            return ['empty' => true];
        }

        return [
            'empty' => false,
            'set_at' => $grant->scratch_at?->toIso8601String(),
            'summary' => $grant->scratch['summary'] ?? '',
            'ops' => $grant->scratch['ops'] ?? [],
            ...ScratchSetTool::describe($check),
        ];
    }

    protected function summary(Request $request, array $data): string
    {
        return $data['empty'] ? 'scratch empty' : 'scratch: '.count($data['changes']).' numbers';
    }
}
