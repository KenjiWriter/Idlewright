<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentLevel;
use App\Agent\AgentPlans;
use App\Agent\ToolRefused;
use App\Filament\Pages\AgentPlansPage;
use App\Models\AgentPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/** The state of a plan of the assistant: the decision of the person, their note, whether the drafts changed under it, and the numbers the server measured. */
final class PlanStatusTool extends AgentTool
{
    protected AgentLevel $level = AgentLevel::Plan;

    protected string $name = 'plan_status';

    protected string $title = 'Read the state of a plan';

    protected string $description = 'The state of a plan of this game (the last one submitted for the person you work for when no number is given): submitted, approved (until when), approval_expired, rejected (with the reason) or superseded (by which version); whether it is outdated (the drafts changed since, so recount it and submit a new version); the note the operator left for you; and the numbers the server measured (targets on live and on the plan, gates, the pace of players A, B and C).';

    public function __construct(private readonly AgentContext $context) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan' => $schema->integer()->min(1)->description('The number of the plan.'),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate(['plan' => ['nullable', 'integer', 'min:1']]);
        $grant = $this->context->grant() ?? throw new ToolRefused('A plan belongs to a consent of the panel; a tool run from the command line has none.');

        $query = AgentPlan::query()->where('game_id', $grant->game_id);
        $plan = isset($input['plan'])
            ? $query->find($input['plan'])
            : $query->where('admin_id', $grant->admin_id)->latest('id')->first();

        if ($plan === null) {
            throw new ToolRefused(isset($input['plan']) ? "There is no plan {$input['plan']} in this game." : 'You have submitted no plan in this game yet.');
        }

        return [...app(AgentPlans::class)->statusOf($plan), 'link' => AgentPlansPage::getUrl(['plan' => $plan->id], panel: 'admin')];
    }

    protected function summary(Request $request, array $data): string
    {
        return "plan {$data['plan']} v{$data['version']}: {$data['state']}".($data['outdated'] ? ', outdated' : '');
    }
}
