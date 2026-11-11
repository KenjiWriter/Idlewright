<?php

declare(strict_types=1);

namespace App\Agent\Tools;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class TuningBudgetTool extends StudyTool
{
    protected string $name = 'tuning_budget';

    protected string $title = 'The bonuses of the parts (tuning)';

    protected string $description = 'The best set of the bonuses of the tuning of the parts (tuning:budget): how much of a lap and of the income it takes, against the ceiling the owner approved. About a second.';
}
