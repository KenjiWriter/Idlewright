<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class FleetStudyTool extends StudyTool
{
    protected string $name = 'fleet_study';

    protected string $title = 'The fleet and the VIP account';

    protected string $description = 'The limit of the cars on the road and what the VIP account adds (economy:fleet): the day each kind of player buys each rung as the anchors have it, free under the limit, and with the VIP account. A report as text.';

    protected function studySchema(JsonSchema $schema): array
    {
        return [
            'players' => $schema->array()->items($schema->string()->enum(['A', 'B', 'C']))->description('Play only these kinds of players (all when left out).'),
        ];
    }

    protected function studyRules(): array
    {
        return [
            'players' => ['nullable', 'array'],
            'players.*' => ['in:A,B,C'],
        ];
    }
}
