<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentLevel;
use App\Agent\AgentPlanExecution;
use App\Models\AgentGrant;
use App\Models\AgentPlan;

/** Undoes the writing of a plan: its drafts go back to what they were, while nobody has worked on them since. */
final class PlanUndoTool extends PlanTool
{
    protected AgentLevel $level = AgentLevel::Plan;

    protected string $name = 'plan_undo';

    protected string $title = 'Undo the writing of a plan';

    protected string $description = 'Undoes the drafts a plan wrote: every draft goes back to what it was before. It is refused when someone worked on those drafts since (they are listed), or while a pack of them is being made. Use it when the report shows a difference, or when the operator asks; a plan that was undone cannot be written again, so submit a new version.';

    protected function act(AgentPlan $plan, AgentGrant $grant): void
    {
        app(AgentPlanExecution::class)->undo($plan, $grant->admin);
    }
}
