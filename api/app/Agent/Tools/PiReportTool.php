<?php

declare(strict_types=1);

namespace App\Agent\Tools;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class PiReportTool extends StudyTool
{
    protected string $name = 'pi_report';

    protected string $title = 'The PI of the cars and their classes';

    protected string $description = 'The performance index (PI, 0-999) of every car of the catalog as it comes and its class (competition:pi-report). About a second.';
}
