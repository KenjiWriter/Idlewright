<?php

declare(strict_types=1);

namespace App\Agent\Tools;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class GigsReportTool extends StudyTool
{
    protected string $name = 'gigs_report';

    protected string $title = 'What gigs pay an hour';

    protected string $description = 'What the gigs (side jobs) pay an hour for every car that can take them (gigs:report), against the rung of the ladder of the car and against its laps, with the rows out of the bounds of the design. About a second; the report is long.';
}
