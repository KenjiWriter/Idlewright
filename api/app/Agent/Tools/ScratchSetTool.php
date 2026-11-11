<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentScratch;
use App\Agent\ToolRefused;
use App\Content\Bridge\BridgeIssue;
use App\Content\Bridge\ChangeCheck;
use App\Content\Bridge\ResolvedChange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/**
 * Sets the scratch of the assistant: a block of changes that is checked as the bridge checks one and kept under the consent when nothing in it is refused, but never saved
 * as drafts. The simulators count on it with the variant `scratch`.
 */
final class ScratchSetTool extends AgentTool
{
    protected string $name = 'scratch_set';

    protected string $title = 'Set the scratch ("what if")';

    protected string $description = 'Replaces the scratch with a block of changes that is never saved: the operations of the bridge format ride-changes/1 (set, scale, text, create, add, insert) on rules:starter, rules:tutorial, rules:vocabulary, rules:missions, cars and parts. The server checks it as the bridge does and answers every number it changes (before, published, after, unit) or what is refused and why; a refused block is not kept. Then count it with any simulation tool and variant "scratch". Example op: {"op":"set","target":{"rules":"starter"},"path":"/player/early_xp/3","value":900,"unit":"plain"}. Read pointers and units with rules_read first; never invent them.';

    /** The services are made when the tool runs: listing the tools must work in a game that has no live pack yet. */
    public function __construct(private readonly AgentContext $context) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->description('One sentence in the language of the operator: what the scratch tries.')->required(),
            'ops' => $schema->array()->items($schema->object())->min(1)->max(AgentScratch::MAX_OPS)->description('The operations, in the format of the ops of ride-changes/1, applied in order.')->required(),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate([
            'summary' => ['required', 'string', 'max:300'],
            'ops' => ['required', 'array', 'min:1', 'max:'.AgentScratch::MAX_OPS],
            'ops.*' => ['array'],
        ]);

        $grant = $this->context->grant() ?? throw new ToolRefused('The scratch belongs to a consent of the panel; a tool run from the command line has none.');

        return self::describe(app(AgentScratch::class)->set($grant, $input['summary'], array_values($input['ops'])));
    }

    protected function summary(Request $request, array $data): string
    {
        return $data['kept'] ? 'scratch set: '.count($data['changes']).' numbers' : 'scratch refused: '.count($data['errors']).' errors';
    }

    /**
     * What a check of the scratch says, for the assistant.
     *
     * @return array<string, mixed>
     */
    public static function describe(ChangeCheck $check): array
    {
        return [
            'kept' => $check->ok(),
            'changes' => array_map(static fn (ResolvedChange $change): array => [
                'document' => $change->document,
                'pointer' => $change->pointer(),
                'what' => $change->label,
                'unit' => $change->unit?->value,
                'published' => $change->publishedText(),
                'before' => $change->beforeText(),
                'after' => $change->afterText(),
                'percent' => round($change->percent(), 2),
            ], $check->changes),
            'errors' => array_map(static fn (BridgeIssue $issue): array => $issue->toArray(), $check->errors()),
            'remarks' => array_map(static fn (BridgeIssue $issue): array => $issue->toArray(), $check->warnings()),
            'repair' => $check->repairMessage(),
        ];
    }
}
