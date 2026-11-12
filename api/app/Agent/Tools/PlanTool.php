<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentPlans;
use App\Agent\PlanRefused;
use App\Agent\ToolRefused;
use App\Filament\Pages\AgentPlansPage;
use App\Models\AgentGrant;
use App\Models\AgentPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/**
 * A tool that does something with one plan of the game (write it, undo it, ask for a pack after it): it finds the plan, does the work on behalf of the person of the
 * consent, and answers the state of the plan as `plan_status` does, with the reason in plain English when the plan cannot go on.
 */
abstract class PlanTool extends AgentTool
{
    public function __construct(protected readonly AgentContext $context) {}

    /** What the tool does with the plan. @throws PlanRefused */
    abstract protected function act(AgentPlan $plan, AgentGrant $grant): void;

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan' => $schema->integer()->min(1)->description('The number of the plan.')->required(),
        ];
    }

    protected function run(Request $request): array
    {
        $input = $request->validate(['plan' => ['required', 'integer', 'min:1']]);
        $grant = $this->context->grant() ?? throw new ToolRefused('A plan belongs to a consent of the panel; a tool run from the command line has none.');
        $plan = AgentPlan::query()->where('game_id', $grant->game_id)->find($input['plan']) ?? throw new ToolRefused("There is no plan {$input['plan']} in this game.");
        $person = $grant->admin ?? throw new ToolRefused('The person of this consent is gone.');

        try {
            $this->act($plan, $grant->setRelation('admin', $person));
        } catch (PlanRefused $refused) {
            throw new ToolRefused($refused->explain());
        }

        return [...app(AgentPlans::class)->statusOf($plan->refresh()), 'link' => AgentPlansPage::getUrl(['plan' => $plan->id], panel: 'admin')];
    }

    protected function summary(Request $request, array $data): string
    {
        return "{$this->name()} plan {$data['plan']}: {$data['state']}";
    }
}
