<?php

declare(strict_types=1);

namespace App\Agent\Tools;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class RunGatesTool extends StudyTool
{
    protected string $name = 'run_gates';

    protected string $title = 'The five gates of publication';

    protected string $description = 'Runs the five gates a pack must pass before it can be made (tracks:simulate --strict, economy:simulate --strict --strict-game, missions:budget --strict, tuning:budget --strict, gigs:report --strict): passed or not, and what a failed gate said. Long (up to minutes): counted by the worker, one long study at a time in a game; if it is not done in time you get a job number for job_result.';
}
