<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/** A simulator of the game as a tool of the assistant (see `StudyTool` and `App\Agent\AgentStudies`). */
final class SimulateEconomyTool extends StudyTool
{
    protected string $name = 'simulate_economy';

    protected string $title = 'The economy simulator (players A, B, C)';

    protected string $description = 'The economy simulator (economy:simulate): the day each kind of player (A plays 1.5 h a day, B 0.5 h, C 4 h) buys each car of the ladder against the days of the design (the pace gate), the ladder of the game against the anchors (income and experience of each level), the fleet, and whether the numbers of the rules agree with the anchors (consistency). Takes about a second. Set full to true for the whole report (extras, upkeep), which is long.';

    protected function studySchema(JsonSchema $schema): array
    {
        return [
            'players' => $schema->array()->items($schema->string()->enum(['A', 'B', 'C']))->description('Play only these kinds of players (all when left out).'),
            'payers' => $schema->boolean()->description('Add the payers table (missions, VIP, auto-refill, pass against a player who pays nothing).'),
            'gigs' => $schema->boolean()->description('Add the gigs table and the shift of the days of the ladder they cause.'),
            'full' => $schema->boolean()->description('The whole report, with the extras and the upkeep. Default false.'),
        ];
    }

    protected function studyRules(): array
    {
        return [
            'players' => ['nullable', 'array'],
            'players.*' => ['in:A,B,C'],
            'payers' => ['nullable', 'boolean'],
            'gigs' => ['nullable', 'boolean'],
            'full' => ['nullable', 'boolean'],
        ];
    }
}
