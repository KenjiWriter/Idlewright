<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentLevel;
use App\Agent\AgentPlanExecution;
use App\Models\AgentGrant;
use App\Models\AgentPlan;

/** Writes an approved plan as drafts (D9 of the design), exactly as it was approved, and has the server count the drafts again for the report. */
final class PlanExecuteTool extends PlanTool
{
    protected AgentLevel $level = AgentLevel::Plan;

    protected string $name = 'plan_execute';

    protected string $title = 'Write an approved plan as drafts';

    protected string $description = 'Writes the block of an approved plan as drafts, on behalf of the person of your consent, exactly as it was approved: through the check of the bridge, in one transaction, all or nothing. It is refused when the plan is not approved (only a person approves it, on its page), when the approval expired (24 hours), or when the drafts changed since it was submitted. Nothing is published: the drafts wait for a pack. Then the server counts the drafts again; read the report (promised against measured) with plan_status, and tell the operator. A difference between them is an alarm.';

    protected function act(AgentPlan $plan, AgentGrant $grant): void
    {
        app(AgentPlanExecution::class)->execute($plan, $grant->admin);
    }
}
