<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class StreaksBudgetTool extends StudyTool
{
    protected string $name = 'streaks_budget';

    protected string $title = 'The streaks of days';

    protected string $description = 'The rewards of the streaks of days of play (streaks:budget) for a month, measured in tanks of a car on a track; with simulate it plays the streak in the economy simulator and says whether it moves the day a car is bought by more than 5%. A report as text.';

    protected function studySchema(JsonSchema $schema): array
    {
        return [
            'car' => $schema->string()->description('The car whose tank is the measure (default rdzak-gt-4).'),
            'track' => $schema->string()->description('The track it drives (default zlomowo-street).'),
            'days' => $schema->integer()->min(1)->max(90)->description('The days of the month to add up (default 30).'),
            'simulate' => $schema->boolean()->description('Also play it in the economy simulator.'),
        ];
    }

    protected function studyRules(): array
    {
        return [
            'car' => ['nullable', 'string', 'max:64'],
            'track' => ['nullable', 'string', 'max:64'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'simulate' => ['nullable', 'boolean'],
        ];
    }
}
