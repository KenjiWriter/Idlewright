<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Services\PendingChanges;
use Laravel\Mcp\Request;

/**
 * What waits for a pack: every document with a draft, whether it differs from what is published, and its changes as the page "Zmiany do publikacji" says them.
 */
final class DraftsListTool extends AgentTool
{
    protected string $name = 'drafts_list';

    protected string $title = 'List the drafts that wait';

    protected string $description = 'The drafts of the game that wait for a pack: each document (car, part, rules), whether it differs from what is published, when it was saved, and its changes (where, from, to), as the page "Zmiany do publikacji" shows them.';

    public function __construct(private readonly PendingChanges $pending) {}

    protected function run(Request $request): array
    {
        $drafts = [];

        foreach ($this->pending->all() as $entry) {
            $definition = $entry['definition'];
            $drafts[] = [
                'kind' => $definition->kind,
                'slug' => $definition->slug,
                'state' => $entry['state'],
                'changes_count' => $entry['count'],
                'saved_at' => $definition->draft_saved_at?->toIso8601String(),
                'changes' => array_map(static fn (array $change): array => ['where' => $change['label'], 'from' => $change['from'], 'to' => $change['to']], $entry['changes']),
            ];
        }

        return ['count' => count($drafts), 'drafts' => $drafts];
    }

    protected function summary(Request $request, array $data): string
    {
        return "drafts: {$data['count']}";
    }
}
