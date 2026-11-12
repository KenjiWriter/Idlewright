<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentLevel;
use App\Agent\AgentPlanExecution;
use App\Models\AgentGrant;
use App\Models\AgentPlan;

/** Asks for a pack after a plan was written (D10 of the design): only at the third level, for a publisher, and only when every draft of the game is the plan's. */
final class PackOrderTool extends PlanTool
{
    protected AgentLevel $level = AgentLevel::Pack;

    protected string $name = 'pack_order';

    protected string $title = 'Ask for a pack after a plan';

    protected string $description = 'Asks the panel to make a pack of the drafts after a plan was written ("Zrób paczkę"), with a note that you asked for it on behalf of the person of your consent. Only a consent of level 3 given by a publisher may, and only when every draft of the game is one the plan wrote; other drafts are listed, and then ask the operator. The pack goes through the gates as always. Making it live ("Uruchom") is always done by a person: end your report with the number of the pack, that it waits for "Uruchom", and the link.';

    protected function act(AgentPlan $plan, AgentGrant $grant): void
    {
        app(AgentPlanExecution::class)->orderPack($plan, $grant->admin);
    }
}
