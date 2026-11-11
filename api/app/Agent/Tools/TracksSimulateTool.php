<?php

declare(strict_types=1);

namespace App\Agent\Tools;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class TracksSimulateTool extends StudyTool
{
    protected string $name = 'tracks_simulate';

    protected string $title = 'The best builds of the tracks';

    protected string $description = 'The best build of parts for every car on every track (tracks:simulate): which car wins which track, whether every tire asks for its surface and the terrain counts. Takes about 10 seconds or more: counted by the worker, one long study at a time in a game; if it is not done in time you get a job number for job_result. The report is long.';
}
