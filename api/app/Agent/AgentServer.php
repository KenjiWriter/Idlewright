<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\Tools\AgentTool;
use App\Agent\Tools\CatalogListTool;
use App\Agent\Tools\CheckTargetsTool;
use App\Agent\Tools\ClassJumpTool;
use App\Agent\Tools\DefinitionReadTool;
use App\Agent\Tools\DraftsListTool;
use App\Agent\Tools\FleetStudyTool;
use App\Agent\Tools\GameOverviewTool;
use App\Agent\Tools\GigsReportTool;
use App\Agent\Tools\HelpLookupTool;
use App\Agent\Tools\JobResultTool;
use App\Agent\Tools\MissionsBudgetTool;
use App\Agent\Tools\PiReportTool;
use App\Agent\Tools\PlanStatusTool;
use App\Agent\Tools\PlanSubmitTool;
use App\Agent\Tools\RulesOfWorkTool;
use App\Agent\Tools\RulesReadTool;
use App\Agent\Tools\RunGatesTool;
use App\Agent\Tools\ScratchClearTool;
use App\Agent\Tools\ScratchReadTool;
use App\Agent\Tools\ScratchSetTool;
use App\Agent\Tools\SimulateCarsTool;
use App\Agent\Tools\SimulateEconomyTool;
use App\Agent\Tools\SimulateProgressTool;
use App\Agent\Tools\StreaksBudgetTool;
use App\Agent\Tools\TracksSimulateTool;
use App\Agent\Tools\TransmuteStudyTool;
use App\Agent\Tools\TuningBudgetTool;
use App\Agent\Tools\TutorialReadTool;
use App\Agent\Tools\WorldReadTool;
use Laravel\Mcp\Server;

/**
 * The connection of the AI assistant to the panel (most AI v2): a server of MCP on the host of the panel, which an app of Claude reaches with a key of OAuth that a person
 * gave it (see `AgentGrants`). Its instructions are the rules of work, and its tools are the only way the assistant sees or changes anything: no tool gives a player's
 * data, and none makes a pack live.
 */
final class AgentServer extends Server
{
    /** Every tool of the assistant, in the order the client lists them; each shows only at the level of the consent it needs. */
    public const TOOLS = [
        RulesOfWorkTool::class,
        GameOverviewTool::class,
        RulesReadTool::class,
        CatalogListTool::class,
        DefinitionReadTool::class,
        TutorialReadTool::class,
        WorldReadTool::class,
        HelpLookupTool::class,
        DraftsListTool::class,
        ScratchSetTool::class,
        ScratchReadTool::class,
        ScratchClearTool::class,
        SimulateCarsTool::class,
        SimulateEconomyTool::class,
        RunGatesTool::class,
        MissionsBudgetTool::class,
        TuningBudgetTool::class,
        GigsReportTool::class,
        StreaksBudgetTool::class,
        FleetStudyTool::class,
        TransmuteStudyTool::class,
        PiReportTool::class,
        ClassJumpTool::class,
        TracksSimulateTool::class,
        SimulateProgressTool::class,
        CheckTargetsTool::class,
        JobResultTool::class,
        PlanSubmitTool::class,
        PlanStatusTool::class,
    ];

    protected string $name = 'Idlewright';

    protected string $version = '2.0.0-v3';

    protected array $tools = self::TOOLS;

    public function __construct(Server\Contracts\Transport $transport)
    {
        parent::__construct($transport);
        $this->instructions = AgentRules::text();
    }

    /**
     * The names of the tools a level of consent has.
     *
     * @return list<string>
     */
    public static function toolNamesFor(AgentLevel $level): array
    {
        $names = [];

        foreach (self::TOOLS as $class) {
            /** @var AgentTool $tool */
            $tool = app($class);

            if ($level->covers($tool->requiredLevel())) {
                $names[] = $tool->name();
            }
        }

        return $names;
    }
}
