<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentLevel;
use App\Agent\AgentPlans;
use App\Agent\ToolRefused;
use App\Filament\Pages\AgentPlansPage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/**
 * Submits the scratch as a plan for a person of the panel to decide on (D8 of the design). The server keeps the block exactly as it is, measures the plan itself and
 * gives the link to its page; only a person approves it there.
 */
final class PlanSubmitTool extends AgentTool
{
    protected AgentLevel $level = AgentLevel::Plan;

    protected string $name = 'plan_submit';

    protected string $title = 'Submit the scratch as a plan';

    protected string $description = 'Submits your scratch as a plan for the operator to approve in the panel. Give the goal in the operator\'s words, the targets in numbers (the same form as check_targets), the strategy, the options you rejected and why, the levers (what the plan means to change: rules:starter#<section>, rules:tutorial, rules:vocabulary, rules:missions, kind:part, kind:vehicle, part:<slug>, vehicle:<slug>), the assumptions, the risks and what you did not check. The server keeps the block, measures the plan itself (targets on live and on the plan, gates, the pace of players A, B and C) and answers the link to its page. A plan never changes the game by itself: a person approves it on that page, and writing the drafts comes after. To correct a plan, submit a new version with supersedes.';

    public function __construct(private readonly AgentContext $context) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('A short title, in the operator\'s language.')->required(),
            'goal' => $schema->string()->description('The goal in the operator\'s words.')->required(),
            'targets' => $schema->array()->items($schema->object())->min(1)->max(20)->description('The targets in numbers: level_at_minute, can_afford, days_to_car, gates (see check_targets).')->required(),
            'strategy' => $schema->string()->description('How the plan reaches the goal and what it leaves untouched.')->required(),
            'levers' => $schema->array()->items($schema->string())->min(1)->max(20)->description('What the plan means to change, e.g. ["rules:starter#player"]. Changes outside the levers are marked red on the page.')->required(),
            'alternatives' => $schema->array()->items($schema->object())->max(5)->description('The options you rejected: [{"option": "...", "why": "..."}].'),
            'assumptions' => $schema->string()->description('The way of playing, the car, whether the tutorial counts, and anything else the numbers rest on.'),
            'risks' => $schema->string()->description('What could go wrong.'),
            'unverified' => $schema->string()->description('What you did not check.'),
            'supersedes' => $schema->integer()->min(1)->description('The plan this one corrects (it becomes a new version of it).'),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'goal' => ['required', 'string', 'max:2000'],
            'targets' => ['required', 'array', 'min:1', 'max:20'],
            'strategy' => ['required', 'string', 'max:6000'],
            'levers' => ['required', 'array', 'min:1', 'max:20'],
            'levers.*' => ['string', 'max:80'],
            'alternatives' => ['nullable', 'array', 'max:5'],
            'alternatives.*.option' => ['required', 'string', 'max:500'],
            'alternatives.*.why' => ['required', 'string', 'max:1000'],
            'assumptions' => ['nullable', 'string', 'max:3000'],
            'risks' => ['nullable', 'string', 'max:3000'],
            'unverified' => ['nullable', 'string', 'max:3000'],
            'supersedes' => ['nullable', 'integer', 'min:1'],
        ]);

        $grant = $this->context->grant() ?? throw new ToolRefused('A plan belongs to a consent of the panel; a tool run from the command line has none.');
        $plans = app(AgentPlans::class);
        $plan = $plans->submit($grant, $input);

        return [
            ...$plans->statusOf($plan),
            'link' => AgentPlansPage::getUrl(['plan' => $plan->id], panel: 'admin'),
            'changes_count' => count($plan->changes),
            'next' => 'Give the operator the link. Only a person approves a plan, on its page; a "yes" in the chat is not an approval. Read plan_status for the decision, the note of the operator and the numbers the server measured.',
        ];
    }

    protected function summary(Request $request, array $data): string
    {
        return "plan {$data['plan']} v{$data['version']}: {$data['state']}, ".$data['changes_count'].' changes';
    }
}
