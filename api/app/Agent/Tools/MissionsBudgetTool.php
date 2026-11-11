<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class MissionsBudgetTool extends StudyTool
{
    protected string $name = 'missions_budget';

    protected string $title = 'What the missions ask and pay';

    protected string $description = 'What the missions of a month ask and pay (missions:budget) for a car on a track, against the share of the cash and the experience the design allows. About a second.';

    protected function studySchema(JsonSchema $schema): array
    {
        return [
            'car' => $schema->string()->description('The car whose tank is the measure (default rdzak-gt-4).'),
            'track' => $schema->string()->description('The track it drives (default zlomowo-street).'),
            'level' => $schema->integer()->min(1)->max(99)->description('The level of the driver, which decides what templates fit (default 1).'),
        ];
    }

    protected function studyRules(): array
    {
        return [
            'car' => ['nullable', 'string', 'max:64'],
            'track' => ['nullable', 'string', 'max:64'],
            'level' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
